<?php

namespace Tests\Feature;

use App\Models\MockInsurerRecord;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_command_reconciles_pending_and_timeout_policies(): void
    {
        // 1. Create a policy stuck in NEEDS_RECONCILIATION
        $req = PolicyRequest::create([
            'client_request_id' => 'CMD-RECON-01',
            'agent_id' => 'AGENT-CMD',
            'customer_name' => 'Command Test Customer',
            'date_of_birth' => '1995-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 5,
            'calculated_premium' => 60.00,
            'pricing_breakdown' => ['base' => 12],
            'status' => PolicyRequest::STATUS_NEEDS_RECONCILIATION,
            'provider_reference' => 'PRV-CMD-01',
        ]);

        // 2. Mock insurer has confirmed policy issued
        MockInsurerRecord::create([
            'provider_reference' => 'PRV-CMD-01',
            'client_request_id' => 'CMD-RECON-01',
            'agent_id' => 'AGENT-CMD',
            'policy_number' => 'POL-CMD-SUCCESS',
            'status' => 'ISSUED',
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
            'premium' => 60.00,
        ]);

        // 3. Run artisan policyflow:reconcile command
        $this->artisan('policyflow:reconcile')
            ->expectsOutputToContain('Starting PolicyFlow reconciliation process...')
            ->expectsOutputToContain('POL-CMD-SUCCESS')
            ->assertSuccessful();

        $req->refresh();
        $this->assertEquals(PolicyRequest::STATUS_ISSUED, $req->status);
        $this->assertEquals('POL-CMD-SUCCESS', $req->policy_number);
    }
}
