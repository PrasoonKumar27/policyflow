<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Services\PolicyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected PolicyStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new PolicyStateMachine();
    }

    public function test_allows_valid_state_transitions(): void
    {
        $req = PolicyRequest::create([
            'client_request_id' => 'SM-001',
            'agent_id' => 'AGENT-1',
            'customer_name' => 'State Test',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 5,
            'calculated_premium' => 60.00,
            'pricing_breakdown' => ['base' => 12],
            'status' => PolicyRequest::STATUS_VALIDATED,
        ]);

        // VALIDATED -> SUBMITTED
        $this->stateMachine->transition($req, PolicyRequest::STATUS_SUBMITTED);
        $this->assertEquals(PolicyRequest::STATUS_SUBMITTED, $req->fresh()->status);

        // SUBMITTED -> ISSUED
        $this->stateMachine->transition($req, PolicyRequest::STATUS_ISSUED);
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $req->fresh()->status);
    }

    public function test_strictly_rejects_regression_out_of_issued_state(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $req = PolicyRequest::create([
            'client_request_id' => 'SM-002',
            'agent_id' => 'AGENT-1',
            'customer_name' => 'State Test',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 5,
            'calculated_premium' => 60.00,
            'pricing_breakdown' => ['base' => 12],
            'status' => PolicyRequest::STATUS_ISSUED,
            'policy_number' => 'POL-IMMUTABLE-01',
        ]);

        // Attempt illegal regression: ISSUED -> SUBMITTED
        $this->stateMachine->transition($req, PolicyRequest::STATUS_SUBMITTED);
    }
}
