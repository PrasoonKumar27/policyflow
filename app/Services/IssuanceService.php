<?php

namespace App\Services;

use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IssuanceService
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        protected MockInsurerClient $insurerClient,
        protected PolicyStateMachine $stateMachine
    ) {}

    /**
     * Start or safely resume issuance for a policy request.
     */
    public function submit(PolicyRequest $policyRequest, ?string $scenario = null): PolicyRequest
    {
        return DB::transaction(function () use ($policyRequest, $scenario) {
            /** @var PolicyRequest $request */
            $request = PolicyRequest::where('id', $policyRequest->id)->lockForUpdate()->firstOrFail();

            // Guard: If already issued, return immediately without re-calling external insurer
            if ($request->status === PolicyRequest::STATUS_ISSUED) {
                return $request;
            }

            // Increment attempt count
            $attemptNumber = $request->attempt_count + 1;
            $request->attempt_count = $attemptNumber;

            // Transition state to SUBMITTED
            $this->stateMachine->transition(
                $request,
                PolicyRequest::STATUS_SUBMITTED,
                "Issuance attempt #{$attemptNumber} started.",
                ['attempt_number' => $attemptNumber, 'scenario' => $scenario]
            );

            // Execute provider call
            $response = $this->insurerClient->issuePolicy($request, $scenario);

            // Log attempt record
            $attempt = IssuanceAttempt::create([
                'policy_request_id' => $request->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'request_payload' => [
                    'client_request_id' => $request->client_request_id,
                    'customer_name' => $request->customer_name,
                    'premium' => $request->calculated_premium,
                    'plan_code' => $request->plan_code,
                ],
                'response_payload' => $response['raw_response'] ?? null,
                'status' => $response['status'],
                'http_status' => $response['http_status'] ?? null,
                'error_message' => $response['error'] ?? null,
                'provider_reference' => $response['provider_reference'] ?? $request->provider_reference,
            ]);

            // Handle provider response outcomes
            if (!empty($response['provider_reference'])) {
                $request->provider_reference = $response['provider_reference'];
            }
            $request->last_response = $response['raw_response'] ?? null;

            if (!empty($response['is_timeout'])) {
                // Reliability Scenario: TIMEOUT_AFTER_ISSUE
                // Upstream might have processed the request, so we MUST NOT fail or reissue blindly!
                $request->last_error = $response['error'];
                $request->save();

                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_NEEDS_RECONCILIATION,
                    'Upstream insurer request timed out. Placed into reconciliation queue to prevent double-issuance.',
                    ['attempt_id' => $attempt->id]
                );

                return $request;
            }

            if (($response['status'] ?? null) === 'PENDING') {
                // Reliability Scenario: DELAYED_CALLBACK
                $request->last_error = null;
                $request->save();

                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_PENDING_CONFIRMATION,
                    'Awaiting asynchronous webhook callback event from insurer.',
                    ['attempt_id' => $attempt->id, 'provider_reference' => $request->provider_reference]
                );

                return $request;
            }

            if (!empty($response['success'])) {
                // Reliability Scenario: SUCCESS
                $request->policy_number = $response['policy_number'];
                $request->last_error = null;
                $request->save();

                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_ISSUED,
                    "Policy issued successfully by insurer with number {$response['policy_number']}.",
                    ['policy_number' => $response['policy_number'], 'attempt_id' => $attempt->id]
                );

                return $request;
            }

            // Failure / Temporary error
            $request->last_error = $response['error'] ?? 'Unknown issuance error.';
            $request->save();

            if ($attemptNumber >= self::MAX_ATTEMPTS) {
                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_FAILED,
                    "Max retry attempts ({$attemptNumber}/" . self::MAX_ATTEMPTS . ") exhausted. Terminal failure.",
                    ['attempt_id' => $attempt->id]
                );
            }

            return $request;
        });
    }

    /**
     * Reconcile a request with upstream provider.
     * Essential for TIMEOUT_AFTER_ISSUE and stale PENDING_CONFIRMATION.
     */
    public function reconcile(PolicyRequest $policyRequest): array
    {
        return DB::transaction(function () use ($policyRequest) {
            /** @var PolicyRequest $request */
            $request = PolicyRequest::where('id', $policyRequest->id)->lockForUpdate()->firstOrFail();

            if ($request->status === PolicyRequest::STATUS_ISSUED) {
                return [
                    'status' => 'already_issued',
                    'message' => 'Policy is already in confirmed ISSUED state.',
                    'policy_request' => $request,
                ];
            }

            $searchKey = $request->provider_reference ?: $request->client_request_id;
            $upstream = $this->insurerClient->queryStatus($searchKey);

            if (!$upstream) {
                return [
                    'status' => 'not_found_upstream',
                    'message' => "No record found upstream for {$searchKey}. Safe to retry issuance.",
                    'policy_request' => $request,
                ];
            }

            if ($upstream['status'] === 'ISSUED') {
                $request->policy_number = $upstream['policy_number'];
                $request->provider_reference = $upstream['provider_reference'];
                $request->last_error = null;
                $request->save();

                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_ISSUED,
                    "Reconciled with upstream provider: Policy #{$upstream['policy_number']} was confirmed issued.",
                    ['upstream' => $upstream]
                );

                return [
                    'status' => 'reconciled_issued',
                    'message' => 'Policy reconciled successfully and marked ISSUED.',
                    'policy_request' => $request,
                ];
            }

            if ($upstream['status'] === 'FAILED') {
                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_FAILED,
                    'Reconciliation confirmed upstream issuance failure.',
                    ['upstream' => $upstream]
                );

                return [
                    'status' => 'reconciled_failed',
                    'message' => 'Upstream confirmed issuance failed.',
                    'policy_request' => $request,
                ];
            }

            return [
                'status' => 'still_pending',
                'message' => 'Upstream status is still pending confirmation.',
                'policy_request' => $request,
            ];
        });
    }

    /**
     * Process asynchronous webhook callback from mock insurer.
     * Enforces callback idempotency via unique event_id and immutable ISSUED protection.
     */
    public function processCallback(array $payload): array
    {
        $eventId = $payload['event_id'] ?? null;
        $providerRef = $payload['provider_reference'] ?? null;
        $eventType = $payload['event_type'] ?? 'POLICY_ISSUED';
        $policyNumber = $payload['policy_number'] ?? null;

        if (!$eventId || !$providerRef) {
            throw new Exception("Callback payload missing required 'event_id' or 'provider_reference'.");
        }

        return DB::transaction(function () use ($eventId, $providerRef, $eventType, $policyNumber, $payload) {
            // Check for duplicate callback event using DB uniqueness
            $existingEvent = ProviderEvent::where('event_id', $eventId)->first();
            if ($existingEvent) {
                return [
                    'status' => 'duplicate_ignored',
                    'message' => "Event {$eventId} has already been processed. Duplicate ignored safely.",
                    'event_id' => $eventId,
                ];
            }

            // Record event
            $event = ProviderEvent::create([
                'event_id' => $eventId,
                'provider_reference' => $providerRef,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'PROCESSED',
                'processed_at' => now(),
            ]);

            // Find matching policy request
            /** @var PolicyRequest|null $request */
            $request = PolicyRequest::where('provider_reference', $providerRef)->lockForUpdate()->first();

            if (!$request) {
                $event->status = 'REJECTED';
                $event->save();

                return [
                    'status' => 'unmatched',
                    'message' => "No matching PolicyRequest found for provider_reference: {$providerRef}",
                    'event_id' => $eventId,
                ];
            }

            // Guard: If request is already ISSUED, do NOT let repeated/belated callbacks overwrite it!
            if ($request->status === PolicyRequest::STATUS_ISSUED) {
                return [
                    'status' => 'already_issued',
                    'message' => "Policy {$request->policy_number} is already ISSUED. State preserved without modification.",
                    'policy_request' => $request,
                    'event_id' => $eventId,
                ];
            }

            if ($eventType === 'POLICY_ISSUED') {
                $request->policy_number = $policyNumber ?: ('POL-' . strtoupper(\Illuminate\Support\Str::random(8)));
                $request->save();

                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_ISSUED,
                    "Policy issued via asynchronous provider callback event {$eventId}.",
                    ['event_id' => $eventId, 'policy_number' => $request->policy_number]
                );
            } elseif ($eventType === 'POLICY_FAILED') {
                $request->last_error = $payload['reason'] ?? 'Rejected by external insurer callback.';
                $request->save();

                $request = $this->stateMachine->transition(
                    $request,
                    PolicyRequest::STATUS_FAILED,
                    "Policy failed via asynchronous provider callback event {$eventId}.",
                    ['event_id' => $eventId]
                );
            }

            return [
                'status' => 'processed',
                'message' => "Callback {$eventId} processed successfully.",
                'policy_request' => $request,
                'event_id' => $eventId,
            ];
        });
    }
}
