<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Facades\DB;

class PolicyStateMachine
{
    /**
     * Define valid state transition matrix.
     * Maps current status => allowed next statuses.
     */
    private const ALLOWED_TRANSITIONS = [
        PolicyRequest::STATUS_RECEIVED => [
            PolicyRequest::STATUS_VALIDATED,
            PolicyRequest::STATUS_FAILED,
        ],
        PolicyRequest::STATUS_VALIDATED => [
            PolicyRequest::STATUS_SUBMITTED,
            PolicyRequest::STATUS_FAILED,
        ],
        PolicyRequest::STATUS_SUBMITTED => [
            PolicyRequest::STATUS_ISSUED,
            PolicyRequest::STATUS_PENDING_CONFIRMATION,
            PolicyRequest::STATUS_NEEDS_RECONCILIATION,
            PolicyRequest::STATUS_FAILED,
        ],
        PolicyRequest::STATUS_PENDING_CONFIRMATION => [
            PolicyRequest::STATUS_ISSUED,
            PolicyRequest::STATUS_FAILED,
            PolicyRequest::STATUS_NEEDS_RECONCILIATION,
        ],
        PolicyRequest::STATUS_NEEDS_RECONCILIATION => [
            PolicyRequest::STATUS_ISSUED,
            PolicyRequest::STATUS_FAILED,
            PolicyRequest::STATUS_SUBMITTED, // Allowed if reconciliation confirms safe to retry
        ],
        PolicyRequest::STATUS_FAILED => [
            PolicyRequest::STATUS_SUBMITTED, // Manual/automated safe retry
        ],
        PolicyRequest::STATUS_ISSUED => [
            // STRICTLY TERMINAL: No transitions allowed out of ISSUED!
        ],
    ];

    /**
     * Check whether a transition is allowed.
     */
    public function canTransition(?string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === null) {
            return in_array($toStatus, [PolicyRequest::STATUS_RECEIVED, PolicyRequest::STATUS_VALIDATED], true);
        }

        if ($fromStatus === $toStatus) {
            return true; // Idempotent same-state
        }

        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    /**
     * Perform an atomic state transition with audit history.
     * Uses database locking to guarantee concurrency safety.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(
        PolicyRequest $policyRequest,
        string $targetStatus,
        ?string $reason = null,
        array $metadata = []
    ): PolicyRequest {
        return DB::transaction(function () use ($policyRequest, $targetStatus, $reason, $metadata) {
            // Pessimistically lock row for concurrency safety
            /** @var PolicyRequest $lockedRequest */
            $lockedRequest = PolicyRequest::where('id', $policyRequest->id)->lockForUpdate()->firstOrFail();

            $currentStatus = $lockedRequest->status;

            // If already in target status, idempotent no-op
            if ($currentStatus === $targetStatus) {
                return $lockedRequest;
            }

            if (!$this->canTransition($currentStatus, $targetStatus)) {
                throw new InvalidStateTransitionException(
                    "Illegal state regression/transition attempted from [{$currentStatus}] to [{$targetStatus}] for PolicyRequest #{$lockedRequest->id}."
                );
            }

            $lockedRequest->status = $targetStatus;
            $lockedRequest->save();

            // Record immutable audit history
            PolicyStateHistory::create([
                'policy_request_id' => $lockedRequest->id,
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'reason' => $reason,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            return $lockedRequest;
        });
    }
}
