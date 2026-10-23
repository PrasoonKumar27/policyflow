<?php

namespace App\Http\Controllers;

use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $requests = PolicyRequest::with(['issuanceAttempts', 'stateHistory'])
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total' => $requests->count(),
            'issued' => $requests->where('status', PolicyRequest::STATUS_ISSUED)->count(),
            'needs_recon' => $requests->where('status', PolicyRequest::STATUS_NEEDS_RECONCILIATION)->count(),
            'pending' => $requests->where('status', PolicyRequest::STATUS_PENDING_CONFIRMATION)->count(),
            'failed' => $requests->where('status', PolicyRequest::STATUS_FAILED)->count(),
            'events' => ProviderEvent::count(),
        ];

        return view('dashboard', compact('requests', 'stats'));
    }
}
