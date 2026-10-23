<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function createValidPolicyRequest(string $reqId = 'REL-001', string $agentId = 'AGT-01'): PolicyRequest
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => $reqId,
            'agent_id' => $agentId,
            'customer_name' => 'Reliability Candidate',
            'date_of_birth' => '1995-05-15',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-20',
            'trip_end' => '2026-11-24',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);

        return PolicyRequest::findOrFail($response->json('data.id'));
    }

    /**
     * RELIABILITY CASE 1: DUPLICATE INTAKE
     * Repeated submissions with identical agent_id + client_request_id return HTTP 200
     * and do NOT create duplicate rows or duplicate policies.
     */
    public function test_reliability_case_1_duplicate_intake(): void
    {
        $payload = [
            'client_request_id' => 'AGT-INTAKE-DUP',
            'agent_id' => 'AGENT-INTAKE-01',
            'customer_name' => 'Concurrent Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        // Submission 1: Created
        $res1 = $this->postJson('/api/policy-requests', $payload);
        $res1->assertStatus(201);
        $id = $res1->json('data.id');

        // Submission 2: Repeated intake returns existing
        $res2 = $this->postJson('/api/policy-requests', $payload);
        $res2->assertStatus(200)
            ->assertJsonPath('is_duplicate', true)
            ->assertJsonPath('data.id', $id);

        // Assert database has exactly 1 record
        $this->assertEquals(1, PolicyRequest::where('client_request_id', 'AGT-INTAKE-DUP')->count());
    }

    /**
     * RELIABILITY CASE 2: TIMEOUT AFTER SUCCESS (TIMEOUT_AFTER_ISSUE)
     * Upstream insurer processed and issued policy, but client HTTP request timed out.
     * System transitions to NEEDS_RECONCILIATION without blindly reissuing or failing.
     * Reconciliation endpoint queries provider and safely confirms ISSUED with upstream policy number!
     */
    public function test_reliability_case_2_timeout_after_success_and_reconciliation(): void
    {
        $policy = $this->createValidPolicyRequest('REQ-TMO-99');

        // 1. Submit with TIMEOUT_AFTER_ISSUE scenario
        $submitResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        // Expect HTTP 408 / NEEDS_RECONCILIATION status
        $submitResponse->assertStatus(408)
            ->assertJsonPath('status', PolicyRequest::STATUS_NEEDS_RECONCILIATION);

        $policy->refresh();
        $this->assertEquals(PolicyRequest::STATUS_NEEDS_RECONCILIATION, $policy->status);
        $this->assertNotNull($policy->provider_reference);
        $this->assertNull($policy->policy_number, 'Policy number should not be set yet until confirmed');

        // 2. Trigger reconciliation
        $reconResponse = $this->postJson("/api/policy-requests/{$policy->id}/reconcile");

        $reconResponse->assertStatus(200)
            ->assertJsonPath('status', 'reconciled_issued')
            ->assertJsonPath('data.status', PolicyRequest::STATUS_ISSUED);

        $policy->refresh();
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->status);
        $this->assertNotNull($policy->policy_number);
        $this->assertStringStartsWith('POL-TMO-', $policy->policy_number);

        // 3. Subsequent submit attempts on this already ISSUED policy are no-ops
        $subsequent = $this->postJson("/api/policy-requests/{$policy->id}/submit");
        $subsequent->assertStatus(200)
            ->assertJsonPath('data.policy_number', $policy->policy_number);
    }

    /**
     * RELIABILITY CASE 3: DUPLICATE CALLBACK
     * The same provider event (same event_id) is delivered 3 times.
     * Processing must be idempotent: first event applies, subsequent are flagged duplicate_ignored.
     */
    public function test_reliability_case_3_duplicate_callback_idempotency(): void
    {
        $policy = $this->createValidPolicyRequest('REQ-CB-123');
        $policy->provider_reference = 'PRV-CB-TEST-01';
        $policy->status = PolicyRequest::STATUS_PENDING_CONFIRMATION;
        $policy->save();

        $callbackPayload = [
            'event_id' => 'EVT-DUP-9999',
            'provider_reference' => 'PRV-CB-TEST-01',
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-ASYNC-8888',
            'timestamp' => now()->toIso8601String(),
        ];

        // 1st Callback: Processed successfully
        $cb1 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cb1->assertStatus(200)
            ->assertJsonPath('status', 'processed');

        $policy->refresh();
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->status);
        $this->assertEquals('POL-ASYNC-8888', $policy->policy_number);

        // 2nd Callback (exact duplicate): Ignored safely
        $cb2 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cb2->assertStatus(200)
            ->assertJsonPath('status', 'duplicate_ignored');

        // 3rd Callback (exact duplicate): Ignored safely
        $cb3 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cb3->assertStatus(200)
            ->assertJsonPath('status', 'duplicate_ignored');

        // Verify only 1 provider event record created
        $this->assertEquals(1, ProviderEvent::where('event_id', 'EVT-DUP-9999')->count());
        // Verify state is still ISSUED and unchanged
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->fresh()->status);
    }

    /**
     * RELIABILITY CASE 4: RETRY AFTER TEMPORARY FAILURE
     * Retries must not produce duplicate policies or lose the original accepted premium.
     */
    public function test_reliability_case_4_retry_after_temporary_failure(): void
    {
        $policy = $this->createValidPolicyRequest('REQ-RETRY-01');
        $originalPremium = $policy->calculated_premium;

        // Attempt 1: Provider returns 503 TEMPORARY_FAILURE
        $res1 = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'TEMPORARY_FAILURE',
        ]);
        $res1->assertStatus(200); // Controller returns current status

        $policy->refresh();
        $this->assertEquals(1, $policy->attempt_count);
        $this->assertNull($policy->policy_number);
        $this->assertEquals($originalPremium, $policy->calculated_premium, 'Premium snapshot must remain immutable');

        // Attempt 2: Retry with SUCCESS scenario
        $res2 = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);
        $res2->assertStatus(200)
            ->assertJsonPath('status', PolicyRequest::STATUS_ISSUED);

        $policy->refresh();
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->status);
        $this->assertEquals(2, $policy->attempt_count);
        $this->assertNotNull($policy->policy_number);
        $this->assertEquals($originalPremium, $policy->calculated_premium, 'Original premium retained after retry');

        // Verify exactly 1 policy row exists for this client request
        $this->assertEquals(1, PolicyRequest::where('client_request_id', 'REQ-RETRY-01')->count());
    }

    /**
     * Confirmed ISSUED state cannot be overwritten by repeated requests or callbacks.
     */
    public function test_confirmed_issued_state_cannot_be_overwritten(): void
    {
        $policy = $this->createValidPolicyRequest('REQ-OVERWRITE-01');
        $policy->policy_number = 'POL-ORIGINAL-777';
        $policy->provider_reference = 'PRV-ORIGINAL-777';
        $policy->status = PolicyRequest::STATUS_ISSUED;
        $policy->save();

        // 1. Calling submit again should NOT change policy_number or state
        $res = $this->postJson("/api/policy-requests/{$policy->id}/submit", ['scenario' => 'TEMPORARY_FAILURE']);
        $res->assertStatus(200);
        $this->assertEquals('POL-ORIGINAL-777', $policy->fresh()->policy_number);
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->fresh()->status);

        // 2. Incoming callback with different policy number cannot overwrite
        $cb = $this->postJson('/api/mock-insurer/callback', [
            'event_id' => 'EVT-NEW-ROGUE-01',
            'provider_reference' => 'PRV-ORIGINAL-777',
            'event_type' => 'POLICY_FAILED', // Attempt to fail an already issued policy
        ]);

        $cb->assertStatus(200)
            ->assertJsonPath('status', 'already_issued');

        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $policy->fresh()->status);
        $this->assertEquals('POL-ORIGINAL-777', $policy->fresh()->policy_number);
    }
}
