<?php

namespace Database\Seeders;

use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\PricingEngine;
use Illuminate\Database\Seeder;

class PolicyFlowSeeder extends Seeder
{
    public function run(): void
    {
        $pricingEngine = app(PricingEngine::class);

        $sampleRequests = [
            [
                'client_request_id' => 'AGT-2026-0001',
                'agent_id' => 'AGENT-17',
                'customer_name' => 'Sample Customer',
                'date_of_birth' => '1998-04-10',
                'destination' => 'INTERNATIONAL',
                'trip_start' => '2026-11-10',
                'trip_end' => '2026-11-14',
                'plan_code' => 'PLUS',
                'sum_insured' => 300000,
            ],
            [
                'client_request_id' => 'AGT-2026-0002',
                'agent_id' => 'AGENT-22',
                'customer_name' => 'Aarav Sharma',
                'date_of_birth' => '2010-06-15',
                'destination' => 'DOMESTIC',
                'trip_start' => '2026-12-01',
                'trip_end' => '2026-12-05',
                'plan_code' => 'BASIC',
                'sum_insured' => 100000,
            ],
            [
                'client_request_id' => 'AGT-2026-0003',
                'agent_id' => 'AGENT-09',
                'customer_name' => 'Dr. Meera Patel',
                'date_of_birth' => '1955-01-20',
                'destination' => 'INTERNATIONAL',
                'trip_start' => '2026-12-10',
                'trip_end' => '2026-12-20',
                'plan_code' => 'PREMIUM',
                'sum_insured' => 500000,
            ],
        ];

        foreach ($sampleRequests as $sample) {
            $pricing = $pricingEngine->calculate(
                planCode: $sample['plan_code'],
                sumInsured: $sample['sum_insured'],
                destination: $sample['destination'],
                dateOfBirth: $sample['date_of_birth'],
                tripStart: $sample['trip_start'],
                tripEnd: $sample['trip_end']
            );

            $req = PolicyRequest::create([
                'client_request_id' => $sample['client_request_id'],
                'agent_id' => $sample['agent_id'],
                'customer_name' => $sample['customer_name'],
                'date_of_birth' => $sample['date_of_birth'],
                'destination' => $sample['destination'],
                'trip_start' => $sample['trip_start'],
                'trip_end' => $sample['trip_end'],
                'plan_code' => $sample['plan_code'],
                'sum_insured' => $sample['sum_insured'],
                'travel_days' => $pricing['travel_days'],
                'calculated_premium' => $pricing['final_premium'],
                'pricing_breakdown' => $pricing['breakdown'],
                'status' => PolicyRequest::STATUS_VALIDATED,
            ]);

            PolicyStateHistory::create([
                'policy_request_id' => $req->id,
                'from_status' => null,
                'to_status' => PolicyRequest::STATUS_VALIDATED,
                'reason' => 'Seeded initial validated request.',
                'metadata' => ['premium' => $pricing['final_premium']],
                'created_at' => now(),
            ]);
        }
    }
}
