<?php

namespace App\Console\Commands;

use App\Models\PolicyRequest;
use App\Services\IssuanceService;
use Illuminate\Console\Command;

class ReconcilePoliciesCommand extends Command
{
    protected $signature = 'policyflow:reconcile {--request-id= : Optional specific PolicyRequest ID to reconcile}';
    protected $description = 'Reconcile ambiguous (NEEDS_RECONCILIATION) and stale (PENDING_CONFIRMATION) policy requests with the insurer';

    public function handle(IssuanceService $issuanceService): int
    {
        $this->info('Starting PolicyFlow reconciliation process...');

        $query = PolicyRequest::query();

        if ($specificId = $this->option('request-id')) {
            $query->where('id', $specificId)->orWhere('client_request_id', $specificId);
        } else {
            $query->whereIn('status', [
                PolicyRequest::STATUS_NEEDS_RECONCILIATION,
                PolicyRequest::STATUS_PENDING_CONFIRMATION,
            ]);
        }

        $requests = $query->get();

        if ($requests->isEmpty()) {
            $this->info('No policy requests require reconciliation at this time.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($requests as $req) {
            $result = $issuanceService->reconcile($req);
            $rows[] = [
                $req->id,
                $req->client_request_id,
                $req->provider_reference ?? 'N/A',
                $result['status'],
                $result['policy_request']->status,
                $result['policy_request']->policy_number ?? 'Pending',
            ];
        }

        $this->table(
            ['ID', 'Client Request ID', 'Provider Ref', 'Recon Result', 'Final Status', 'Policy Number'],
            $rows
        );

        $this->info("Reconciliation completed for {$requests->count()} request(s).");
        return self::SUCCESS;
    }
}
