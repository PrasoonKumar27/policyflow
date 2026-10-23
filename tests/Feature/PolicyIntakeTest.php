<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_produces_expected_premium_and_status(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('is_duplicate', false)
            ->assertJsonPath('data.calculated_premium', '225.00')
            ->assertJsonPath('data.status', 'VALIDATED')
            ->assertJsonPath('data.travel_days', 5);

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'status' => 'VALIDATED',
            'calculated_premium' => 225.00,
        ]);
    }

    public function test_invalid_travel_dates_are_rejected(): void
    {
        $payload = [
            'client_request_id' => 'AGT-ERR-001',
            'agent_id' => 'AGENT-1',
            'customer_name' => 'Invalid Dates',
            'date_of_birth' => '1995-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-05', // Earlier than trip_start!
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trip_end']);
    }

    public function test_unsupported_plan_and_coverage_are_rejected(): void
    {
        $payload = [
            'client_request_id' => 'AGT-ERR-002',
            'agent_id' => 'AGENT-1',
            'customer_name' => 'Invalid Plan',
            'date_of_birth' => '1995-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-15',
            'plan_code' => 'PLATINUM_VIP', // Unsupported
            'sum_insured' => 9999999, // Unsupported
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_code', 'sum_insured']);
    }

    public function test_repeated_agent_and_client_request_id_does_not_create_another_logical_request(): void
    {
        $payload = [
            'client_request_id' => 'AGT-DUP-100',
            'agent_id' => 'AGENT-42',
            'customer_name' => 'Idempotent Customer',
            'date_of_birth' => '1992-03-12',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-04',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        // 1st submission: Created (201)
        $first = $this->postJson('/api/policy-requests', $payload);
        $first->assertStatus(201)
            ->assertJsonPath('is_duplicate', false);
        $firstId = $first->json('data.id');

        // 2nd submission with same agent + client_request_id: Returns existing (200)
        $second = $this->postJson('/api/policy-requests', $payload);
        $second->assertStatus(200)
            ->assertJsonPath('status', 'duplicate_acknowledged')
            ->assertJsonPath('is_duplicate', true)
            ->assertJsonPath('data.id', $firstId);

        // Assert only ONE logical record exists in the database
        $this->assertEquals(1, PolicyRequest::where('agent_id', 'AGENT-42')->where('client_request_id', 'AGT-DUP-100')->count());
    }

    public function test_list_requests_supports_filtering_and_pagination(): void
    {
        $this->postJson('/api/policy-requests', [
            'client_request_id' => 'LST-01',
            'agent_id' => 'AGENT-A',
            'customer_name' => 'Alice',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-02',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);

        $response = $this->getJson('/api/policy-requests?agent_id=AGENT-A');
        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'pagination'])
            ->assertJsonCount(1, 'data');
    }
}
