<?php

namespace App\Services;

use App\Models\MockInsurerRecord;
use App\Models\PolicyRequest;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MockInsurerClient
{
    /**
     * Issue policy with upstream mock insurer.
     * Can simulate real HTTP call or direct internal simulation.
     */
    public function issuePolicy(PolicyRequest $request, ?string $scenario = null): array
    {
        $chosenScenario = strtoupper($scenario ?: (request()->header('X-Mock-Scenario') ?: 'SUCCESS'));

        $providerRef = $request->provider_reference ?: 'PRV-' . strtoupper(Str::random(10));

        switch ($chosenScenario) {
            case 'SUCCESS':
                $policyNumber = 'POL-' . strtoupper(Str::random(8));
                MockInsurerRecord::updateOrCreate(
                    ['provider_reference' => $providerRef],
                    [
                        'client_request_id' => $request->client_request_id,
                        'agent_id' => $request->agent_id,
                        'policy_number' => $policyNumber,
                        'status' => 'ISSUED',
                        'scenario' => $chosenScenario,
                        'premium' => $request->calculated_premium,
                        'payload' => ['plan' => $request->plan_code, 'customer' => $request->customer_name],
                    ]
                );

                return [
                    'success' => true,
                    'http_status' => 200,
                    'status' => 'ISSUED',
                    'policy_number' => $policyNumber,
                    'provider_reference' => $providerRef,
                    'raw_response' => [
                        'status' => 'ISSUED',
                        'policy_number' => $policyNumber,
                        'provider_reference' => $providerRef,
                        'issued_at' => now()->toIso8601String(),
                    ],
                ];

            case 'TEMPORARY_FAILURE':
                return [
                    'success' => false,
                    'http_status' => 503,
                    'status' => 'TEMPORARY_FAILURE',
                    'error' => 'Upstream insurer service temporarily unavailable. Backoff & retry.',
                    'raw_response' => [
                        'error' => 'Upstream insurer service temporarily unavailable.',
                        'code' => 'UPSTREAM_503',
                    ],
                ];

            case 'TIMEOUT_AFTER_ISSUE':
                // Upstream provider processed and created policy internally, but network/connection timed out!
                $policyNumber = 'POL-TMO-' . strtoupper(Str::random(8));
                MockInsurerRecord::updateOrCreate(
                    ['provider_reference' => $providerRef],
                    [
                        'client_request_id' => $request->client_request_id,
                        'agent_id' => $request->agent_id,
                        'policy_number' => $policyNumber,
                        'status' => 'ISSUED',
                        'scenario' => $chosenScenario,
                        'premium' => $request->calculated_premium,
                        'payload' => ['plan' => $request->plan_code, 'note' => 'Issued upstream but response timed out'],
                    ]
                );

                return [
                    'success' => false,
                    'is_timeout' => true,
                    'http_status' => 504,
                    'status' => 'TIMEOUT',
                    'provider_reference' => $providerRef,
                    'error' => 'HTTP request timed out after upstream processing began.',
                    'raw_response' => [
                        'error' => 'Gateway Timeout (Simulated)',
                        'provider_reference' => $providerRef,
                    ],
                ];

            case 'DELAYED_CALLBACK':
                MockInsurerRecord::updateOrCreate(
                    ['provider_reference' => $providerRef],
                    [
                        'client_request_id' => $request->client_request_id,
                        'agent_id' => $request->agent_id,
                        'policy_number' => null,
                        'status' => 'PENDING',
                        'scenario' => $chosenScenario,
                        'premium' => $request->calculated_premium,
                        'payload' => ['plan' => $request->plan_code, 'status' => 'QUEUED_FOR_CALLBACK'],
                    ]
                );

                return [
                    'success' => true,
                    'http_status' => 202,
                    'status' => 'PENDING',
                    'provider_reference' => $providerRef,
                    'raw_response' => [
                        'status' => 'PENDING',
                        'provider_reference' => $providerRef,
                        'message' => 'Policy issuance queued asynchronously. Awaiting provider callback event.',
                    ],
                ];

            default:
                throw new Exception("Unknown mock scenario: {$chosenScenario}");
        }
    }

    /**
     * Provider status check for reconciliation.
     * Queries upstream record by provider_reference or client_request_id.
     */
    public function queryStatus(string $providerReferenceOrRequestId): ?array
    {
        $record = MockInsurerRecord::where('provider_reference', $providerReferenceOrRequestId)
            ->orWhere('client_request_id', $providerReferenceOrRequestId)
            ->first();

        if (!$record) {
            return null;
        }

        return [
            'provider_reference' => $record->provider_reference,
            'client_request_id' => $record->client_request_id,
            'policy_number' => $record->policy_number,
            'status' => $record->status,
            'scenario' => $record->scenario,
            'premium' => $record->premium,
        ];
    }
}
