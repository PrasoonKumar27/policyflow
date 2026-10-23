<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPricingConfigurationException;
use App\Http\Requests\StorePolicyRequest;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\IssuanceService;
use App\Services\PolicyStateMachine;
use App\Services\PricingEngine;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PricingEngine $pricingEngine,
        protected IssuanceService $issuanceService,
        protected PolicyStateMachine $stateMachine
    ) {}

    /**
     * List requests with status filter and pagination.
     * GET /api/policy-requests
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query()->with(['issuanceAttempts', 'stateHistory']);

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        $perPage = min((int) ($request->query('per_page', 15)), 100);
        $paginated = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * Create or safely return existing request.
     * POST /api/policy-requests
     */
    public function store(StorePolicyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $agentId = $data['agent_id'];
        $clientRequestId = $data['client_request_id'];

        // Check if request already exists (Idempotent Intake)
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->with(['issuanceAttempts', 'stateHistory'])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'duplicate_acknowledged',
                'message' => 'Request already processed previously. Returning existing policy record safely.',
                'is_duplicate' => true,
                'data' => $existing,
            ], 200);
        }

        try {
            // Calculate deterministic premium snapshot
            $pricing = $this->pricingEngine->calculate(
                planCode: $data['plan_code'],
                sumInsured: $data['sum_insured'],
                destination: $data['destination'],
                dateOfBirth: $data['date_of_birth'],
                tripStart: $data['trip_start'],
                tripEnd: $data['trip_end']
            );
        } catch (InvalidPricingConfigurationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        try {
            $policyRequest = DB::transaction(function () use ($data, $pricing) {
                $record = PolicyRequest::create([
                    'client_request_id' => $data['client_request_id'],
                    'agent_id' => $data['agent_id'],
                    'customer_name' => $data['customer_name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'destination' => strtoupper($data['destination']),
                    'trip_start' => $data['trip_start'],
                    'trip_end' => $data['trip_end'],
                    'plan_code' => strtoupper($data['plan_code']),
                    'sum_insured' => $data['sum_insured'],
                    'travel_days' => $pricing['travel_days'],
                    'calculated_premium' => $pricing['final_premium'],
                    'pricing_breakdown' => $pricing['breakdown'],
                    'status' => PolicyRequest::STATUS_VALIDATED,
                ]);

                // Record initial state history audit log
                PolicyStateHistory::create([
                    'policy_request_id' => $record->id,
                    'from_status' => null,
                    'to_status' => PolicyRequest::STATUS_VALIDATED,
                    'reason' => 'Policy intake validated and premium snapshot persisted.',
                    'metadata' => [
                        'premium' => $pricing['final_premium'],
                        'travel_days' => $pricing['travel_days'],
                    ],
                    'created_at' => now(),
                ]);

                return $record->fresh(['issuanceAttempts', 'stateHistory']);
            });

            return response()->json([
                'status' => 'created',
                'message' => 'Policy request received and validated successfully.',
                'is_duplicate' => false,
                'data' => $policyRequest,
            ], 201);

        } catch (UniqueConstraintViolationException|QueryException $e) {
            // Handle near-simultaneous concurrent race condition at DB level
            $existing = PolicyRequest::where('agent_id', $agentId)
                ->where('client_request_id', $clientRequestId)
                ->with(['issuanceAttempts', 'stateHistory'])
                ->firstOrFail();

            return response()->json([
                'status' => 'duplicate_acknowledged',
                'message' => 'Concurrent duplicate intake resolved safely via database unique constraint.',
                'is_duplicate' => true,
                'data' => $existing,
            ], 200);
        }
    }

    /**
     * Retrieve status, premium, references, attempts, and audit trail.
     * GET /api/policy-requests/{id}
     */
    public function show(string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::where('id', $id)
            ->orWhere('client_request_id', $id)
            ->with(['issuanceAttempts', 'stateHistory'])
            ->first();

        if (!$policyRequest) {
            return response()->json([
                'status' => 'error',
                'message' => "Policy request '{$id}' not found.",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $policyRequest,
        ], 200);
    }

    /**
     * Start or safely resume issuance.
     * POST /api/policy-requests/{id}/submit
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::where('id', $id)
            ->orWhere('client_request_id', $id)
            ->first();

        if (!$policyRequest) {
            return response()->json([
                'status' => 'error',
                'message' => "Policy request '{$id}' not found.",
            ], 404);
        }

        // Scenario can be sent via body param or header X-Mock-Scenario
        $scenario = $request->input('scenario') ?: $request->header('X-Mock-Scenario');

        $updated = $this->issuanceService->submit($policyRequest, $scenario);
        $updated->load(['issuanceAttempts', 'stateHistory']);

        $statusCode = match ($updated->status) {
            PolicyRequest::STATUS_ISSUED => 200,
            PolicyRequest::STATUS_PENDING_CONFIRMATION => 202,
            PolicyRequest::STATUS_NEEDS_RECONCILIATION => 408, // Ambiguous / timeout requiring reconciliation
            PolicyRequest::STATUS_FAILED => 502,
            default => 200,
        };

        return response()->json([
            'status' => $updated->status,
            'message' => "Issuance workflow completed with status: {$updated->status}",
            'data' => $updated,
        ], $statusCode);
    }

    /**
     * Reconcile an ambiguous or pending request with upstream provider.
     * POST /api/policy-requests/{id}/reconcile
     */
    public function reconcile(string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::where('id', $id)
            ->orWhere('client_request_id', $id)
            ->first();

        if (!$policyRequest) {
            return response()->json([
                'status' => 'error',
                'message' => "Policy request '{$id}' not found.",
            ], 404);
        }

        $result = $this->issuanceService->reconcile($policyRequest);
        $result['policy_request']->load(['issuanceAttempts', 'stateHistory']);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'data' => $result['policy_request'],
        ], 200);
    }
}
