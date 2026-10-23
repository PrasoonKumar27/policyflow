<?php

namespace App\Http\Controllers;

use App\Models\MockInsurerRecord;
use App\Services\IssuanceService;
use App\Services\MockInsurerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MockInsurerController extends Controller
{
    public function __construct(
        protected IssuanceService $issuanceService,
        protected MockInsurerClient $mockClient
    ) {}

    /**
     * Receive simulated provider event callback.
     * POST /api/mock-insurer/callback
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_id' => ['required', 'string'],
            'provider_reference' => ['required', 'string'],
            'event_type' => ['nullable', 'string'], // POLICY_ISSUED, POLICY_FAILED
            'policy_number' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'timestamp' => ['nullable', 'string'],
        ]);

        $result = $this->issuanceService->processCallback($payload);

        return response()->json($result, 200);
    }

    /**
     * Upstream provider status lookup for reconciliation.
     * GET /api/mock-insurer/policies/{provider_reference}
     */
    public function queryPolicy(string $providerReference): JsonResponse
    {
        $record = MockInsurerRecord::where('provider_reference', $providerReference)
            ->orWhere('client_request_id', $providerReference)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => "No provider record found for reference: {$providerReference}",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record,
        ], 200);
    }

    /**
     * Helper endpoint to trigger an asynchronous callback event.
     * POST /api/mock-insurer/simulate-callback
     */
    public function triggerCallback(Request $request): JsonResponse
    {
        $providerRef = $request->input('provider_reference');
        $record = MockInsurerRecord::where('provider_reference', $providerRef)->first();

        $eventId = $request->input('event_id') ?: ('EVT-' . strtoupper(Str::random(10)));
        $policyNumber = $record?->policy_number ?: ('POL-' . strtoupper(Str::random(8)));

        if ($record && empty($record->policy_number)) {
            $record->policy_number = $policyNumber;
            $record->status = 'ISSUED';
            $record->save();
        }

        $eventPayload = [
            'event_id' => $eventId,
            'provider_reference' => $providerRef,
            'event_type' => $request->input('event_type', 'POLICY_ISSUED'),
            'policy_number' => $policyNumber,
            'timestamp' => now()->toIso8601String(),
        ];

        $result = $this->issuanceService->processCallback($eventPayload);

        return response()->json([
            'status' => 'simulated',
            'callback_payload' => $eventPayload,
            'handler_result' => $result,
        ], 200);
    }
}
