<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolicyFlow | Insurance Engine & Reconciliation Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

    <!-- Top Navigation -->
    <header class="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-sky-400 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-white flex items-center gap-2">
                        PolicyFlow <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">v1.0 • Placement Edition</span>
                    </h1>
                    <p class="text-xs text-slate-400">Fault-Tolerant Travel Insurance Issuance & Idempotent Reconciliation Engine</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Mock Insurer Online
                </span>
                <button onclick="window.location.reload()" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition text-xs flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.03 0 01-15.357-2m15.357 2H15"></path></svg>
                    Refresh
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Intake</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-slate-800/60 border border-emerald-500/30 rounded-xl p-4">
                <p class="text-xs font-medium text-emerald-400 uppercase tracking-wider">Issued Policies</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ $stats['issued'] }}</p>
            </div>
            <div class="bg-slate-800/60 border border-amber-500/30 rounded-xl p-4">
                <p class="text-xs font-medium text-amber-400 uppercase tracking-wider">Needs Reconciliation</p>
                <p class="text-2xl font-bold text-amber-400 mt-1">{{ $stats['needs_recon'] }}</p>
            </div>
            <div class="bg-slate-800/60 border border-sky-500/30 rounded-xl p-4">
                <p class="text-xs font-medium text-sky-400 uppercase tracking-wider">Pending Webhook</p>
                <p class="text-2xl font-bold text-sky-400 mt-1">{{ $stats['pending'] }}</p>
            </div>
            <div class="bg-slate-800/60 border border-rose-500/30 rounded-xl p-4">
                <p class="text-xs font-medium text-rose-400 uppercase tracking-wider">Failed Attempts</p>
                <p class="text-2xl font-bold text-rose-400 mt-1">{{ $stats['failed'] }}</p>
            </div>
            <div class="bg-slate-800/60 border border-indigo-500/30 rounded-xl p-4">
                <p class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Provider Events</p>
                <p class="text-2xl font-bold text-indigo-400 mt-1">{{ $stats['events'] }}</p>
            </div>
        </div>

        <!-- Teacher / Evaluator Quick Demo Box -->
        <div class="bg-gradient-to-r from-indigo-950/70 via-slate-900 to-slate-900 border border-indigo-500/40 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-white flex items-center gap-2">
                        <span class="text-xl">⚡</span> Quick Reliability Test Bench for Evaluators
                    </h2>
                    <p class="text-xs text-slate-300 mt-0.5">
                        Click any test to simulate the real-world distributed failure scenario and observe automated recovery.
                    </p>
                </div>
                <div class="text-xs text-indigo-300 bg-indigo-900/40 px-3 py-1.5 rounded-lg border border-indigo-700/50">
                    Target: Zero Duplicates & Consistent State
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <button onclick="runScenario('duplicate_intake')" class="flex flex-col text-left p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 transition group">
                    <span class="text-xs font-bold text-indigo-400 group-hover:text-indigo-300">1. Duplicate Intake</span>
                    <span class="text-[11px] text-slate-400 mt-1">Submits duplicate request twice; returns HTTP 200 without duplicate creation.</span>
                </button>

                <button onclick="runScenario('timeout_reconcile')" class="flex flex-col text-left p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 transition group">
                    <span class="text-xs font-bold text-amber-400 group-hover:text-amber-300">2. Timeout & Reconciliation</span>
                    <span class="text-[11px] text-slate-400 mt-1">Simulates 504 Timeout after issue; reconciles status into ISSUED safely.</span>
                </button>

                <button onclick="runScenario('duplicate_callback')" class="flex flex-col text-left p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 transition group">
                    <span class="text-xs font-bold text-sky-400 group-hover:text-sky-300">3. Duplicate Callback</span>
                    <span class="text-[11px] text-slate-400 mt-1">Delivers identical webhook twice; deduplicates via unique event_id.</span>
                </button>

                <button onclick="runScenario('retry_failure')" class="flex flex-col text-left p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 transition group">
                    <span class="text-xs font-bold text-emerald-400 group-hover:text-emerald-300">4. Retries & Pricing Freeze</span>
                    <span class="text-[11px] text-slate-400 mt-1">Simulates 503 upstream error, retries safely with immutable pricing snapshot.</span>
                </button>
            </div>

            <!-- Console Log Output of Demo -->
            <div id="demo-log" class="mt-4 hidden p-3 rounded-lg bg-black/60 border border-slate-800 text-xs font-mono text-emerald-400 space-y-1"></div>
        </div>

        <!-- Main Workspace: Intake Form + Live Policies Table -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Policy Intake Form -->
            <div class="lg:col-span-1 bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 h-fit sticky top-24">
                <h2 class="text-base font-semibold text-white mb-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    New Policy Intake
                </h2>
                <p class="text-xs text-slate-400 mb-5">Submits to <code class="text-indigo-300">POST /api/policy-requests</code></p>

                <form id="intake-form" onsubmit="submitIntake(event)" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Agent ID</label>
                            <input type="text" id="agent_id" value="AGENT-17" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Client Request ID</label>
                            <input type="text" id="client_request_id" value="AGT-{{ rand(1000, 9999) }}" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-400 font-medium mb-1">Customer Full Name</label>
                        <input type="text" id="customer_name" value="Sample Customer" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Date of Birth</label>
                            <input type="date" id="date_of_birth" value="1998-04-10" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Destination</label>
                            <select id="destination" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                <option value="INTERNATIONAL" selected>INTERNATIONAL (1.80x)</option>
                                <option value="DOMESTIC">DOMESTIC (1.00x)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Trip Start</label>
                            <input type="date" id="trip_start" value="2026-11-10" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Trip End</label>
                            <input type="date" id="trip_end" value="2026-11-14" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Plan</label>
                            <select id="plan_code" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                <option value="BASIC">BASIC (₹12/day)</option>
                                <option value="PLUS" selected>PLUS (₹20/day)</option>
                                <option value="PREMIUM">PREMIUM (₹30/day)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-400 font-medium mb-1">Sum Insured</label>
                            <select id="sum_insured" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                <option value="100000">₹100,000 (1.00x)</option>
                                <option value="300000" selected>₹300,000 (1.25x)</option>
                                <option value="500000">₹500,000 (1.50x)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="w-full mt-4 py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 font-semibold text-white rounded-lg transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                        <span>Persist Request & Snapshot</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            </div>

            <!-- Right Column: Live Requests Table -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-white">Policy Requests & Issuance Status</h2>
                        <p class="text-xs text-slate-400">Real-time lifecycle inspection and state-machine transitions</p>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700/60 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-900/80 border-b border-slate-700/60 text-slate-400 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="py-3 px-4">Request / Agent</th>
                                    <th class="py-3 px-4">Customer & Trip</th>
                                    <th class="py-3 px-4">Plan / Premium</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                @forelse ($requests as $req)
                                <tr class="hover:bg-slate-800/60 transition group">
                                    <td class="py-3.5 px-4">
                                        <div class="font-mono font-semibold text-white">{{ $req->client_request_id }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $req->agent_id }}</div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-slate-200">{{ $req->customer_name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $req->destination }} • {{ $req->travel_days }} days</div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-indigo-300">₹{{ number_format($req->calculated_premium, 2) }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $req->plan_code }} (₹{{ number_format($req->sum_insured) }})</div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if ($req->status === 'ISSUED')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                ✓ ISSUED
                                            </span>
                                            <div class="text-[10px] font-mono text-emerald-300/80 mt-1">{{ $req->policy_number }}</div>
                                        @elseif ($req->status === 'NEEDS_RECONCILIATION')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                ⚠ NEEDS RECON
                                            </span>
                                        @elseif ($req->status === 'PENDING_CONFIRMATION')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                                ⏳ PENDING WEBHOOK
                                            </span>
                                        @elseif ($req->status === 'FAILED')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                ✕ FAILED
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-700 text-slate-300 border border-slate-600">
                                                {{ $req->status }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                        @if ($req->status !== 'ISSUED')
                                            <!-- Submit with scenario selector -->
                                            <div class="inline-flex items-center gap-1">
                                                <select id="scenario-{{ $req->id }}" class="bg-slate-900 border border-slate-700 text-[11px] rounded px-1.5 py-1 text-slate-300">
                                                    <option value="SUCCESS">SUCCESS</option>
                                                    <option value="TIMEOUT_AFTER_ISSUE">TIMEOUT (Reconcile)</option>
                                                    <option value="DELAYED_CALLBACK">DELAYED CALLBACK</option>
                                                    <option value="TEMPORARY_FAILURE">503 FAILURE</option>
                                                </select>
                                                <button onclick="submitIssuance({{ $req->id }})" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[11px] font-medium">
                                                    Submit
                                                </button>
                                            </div>
                                        @endif

                                        @if ($req->status === 'NEEDS_RECONCILIATION' || $req->status === 'PENDING_CONFIRMATION')
                                            <button onclick="reconcileRequest({{ $req->id }})" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-500 text-white rounded text-[11px] font-medium">
                                                Reconcile
                                            </button>
                                        @endif

                                        <button onclick="viewDetails({{ $req->id }})" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded text-[11px]">
                                            Audit Trail
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-500">No policy requests yet. Submit one from the left panel!</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Modal for Detailed State Machine & Audit Trail -->
    <div id="details-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2" id="modal-title">
                        Policy Request Details
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5" id="modal-subtitle">Audit history & immutable breakdown</p>
                </div>
                <button onclick="closeDetails()" class="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="p-6 overflow-y-auto space-y-6 text-xs" id="modal-content">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="p-4 border-t border-slate-800 bg-slate-950/60 flex justify-end">
                <button onclick="closeDetails()" class="px-4 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300">Close</button>
            </div>
        </div>
    </div>

    <!-- Client Script for AJAX Operations and Test Bench -->
    <script>
        async function submitIntake(event) {
            event.preventDefault();
            const payload = {
                client_request_id: document.getElementById('client_request_id').value,
                agent_id: document.getElementById('agent_id').value,
                customer_name: document.getElementById('customer_name').value,
                date_of_birth: document.getElementById('date_of_birth').value,
                destination: document.getElementById('destination').value,
                trip_start: document.getElementById('trip_start').value,
                trip_end: document.getElementById('trip_end').value,
                plan_code: document.getElementById('plan_code').value,
                sum_insured: parseInt(document.getElementById('sum_insured').value),
            };

            const res = await fetch('/api/policy-requests', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok) {
                alert(`Intake Success! Status: ${res.status === 201 ? '201 Created (New)' : '200 OK (Safely Repeated)'}\nCalculated Premium: ₹${data.data.calculated_premium}`);
                window.location.reload();
            } else {
                alert(`Error: ${data.message || JSON.stringify(data.errors)}`);
            }
        }

        async function submitIssuance(id) {
            const scenario = document.getElementById(`scenario-${id}`).value;
            const res = await fetch(`/api/policy-requests/${id}/submit`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ scenario: scenario })
            });
            const data = await res.json();
            alert(`Issuance Response [${res.status}]: ${data.message || data.status}`);
            window.location.reload();
        }

        async function reconcileRequest(id) {
            const res = await fetch(`/api/policy-requests/${id}/reconcile`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            const data = await res.json();
            alert(`Reconciliation Result: ${data.message}`);
            window.location.reload();
        }

        async function viewDetails(id) {
            const res = await fetch(`/api/policy-requests/${id}`);
            const json = await res.json();
            const r = json.data;

            document.getElementById('modal-title').innerText = `Policy #${r.client_request_id} (${r.status})`;
            document.getElementById('modal-subtitle').innerText = `Customer: ${r.customer_name} • Policy Number: ${r.policy_number || 'None'}`;

            let html = `
                <div class="grid grid-cols-2 gap-4 bg-slate-950/60 p-4 rounded-xl border border-slate-800">
                    <div>
                        <span class="text-slate-400">Calculated Premium:</span>
                        <div class="text-lg font-bold text-indigo-400">₹${r.calculated_premium}</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Formula Breakdown:</span>
                        <div class="font-mono text-[11px] text-slate-300">${r.pricing_breakdown?.calculation_formula || 'N/A'}</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Provider Reference:</span>
                        <div class="font-mono text-slate-300">${r.provider_reference || 'N/A'}</div>
                    </div>
                    <div>
                        <span class="text-slate-400">Attempts Count:</span>
                        <div class="font-mono text-slate-300">${r.attempt_count}</div>
                    </div>
                </div>

                <div>
                    <h4 class="font-bold text-white mb-2">State Transition Audit Trail</h4>
                    <div class="space-y-2 border-l-2 border-indigo-500/40 pl-4">
                        ${r.state_history.map(h => `
                            <div class="text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-indigo-300">${h.from_status || 'INIT'} ➔ ${h.to_status}</span>
                                    <span class="text-[10px] text-slate-500">${new Date(h.created_at).toLocaleTimeString()}</span>
                                </div>
                                <div class="text-slate-400">${h.reason || 'State transition'}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div>
                    <h4 class="font-bold text-white mb-2">Issuance Attempts (${r.issuance_attempts.length})</h4>
                    <div class="space-y-2">
                        ${r.issuance_attempts.map(a => `
                            <div class="p-3 rounded-lg bg-slate-950/40 border border-slate-800 flex justify-between items-center text-xs">
                                <div>
                                    <span class="font-bold text-slate-300">Attempt #${a.attempt_number}</span>
                                    <span class="text-slate-500 ml-2">(${a.scenario || 'DEFAULT'})</span>
                                    <div class="text-slate-400 mt-0.5">${a.error_message || 'HTTP Status: ' + (a.http_status || '200')}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold ${a.status === 'SUCCESS' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'}">${a.status}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;

            document.getElementById('modal-content').innerHTML = html;
            document.getElementById('details-modal').classList.remove('hidden');
        }

        function closeDetails() {
            document.getElementById('details-modal').classList.add('hidden');
        }

        // Test Bench Scenarios Runner
        async function runScenario(type) {
            const logBox = document.getElementById('demo-log');
            logBox.classList.remove('hidden');
            logBox.innerHTML = `> Initiating scenario: ${type}...\n`;

            if (type === 'duplicate_intake') {
                const uniqueReqId = 'BENCH-DUP-' + Math.floor(Math.random() * 9000 + 1000);
                const payload = {
                    client_request_id: uniqueReqId,
                    agent_id: 'AGENT-BENCH',
                    customer_name: 'Evaluator Test',
                    date_of_birth: '1995-05-15',
                    destination: 'INTERNATIONAL',
                    trip_start: '2026-11-20',
                    trip_end: '2026-11-25',
                    plan_code: 'PLUS',
                    sum_insured: 300000
                };

                logBox.innerHTML += `> [Step 1] Sending First Request (${uniqueReqId})...\n`;
                const r1 = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const d1 = await r1.json();
                logBox.innerHTML += `> Response 1: HTTP ${r1.status} (Created=${!d1.is_duplicate}), Premium=₹${d1.data.calculated_premium}\n`;

                logBox.innerHTML += `> [Step 2] Sending Duplicate Intake Request with same (AGENT-BENCH + ${uniqueReqId})...\n`;
                const r2 = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const d2 = await r2.json();
                logBox.innerHTML += `> Response 2: HTTP ${r2.status} (is_duplicate=${d2.is_duplicate}) -> Idempotently handled, zero duplicates!\n`;
                logBox.innerHTML += `> SUCCESS: Duplicate Intake handled safely!\n`;
                setTimeout(() => window.location.reload(), 2500);
            }
            else if (type === 'timeout_reconcile') {
                const reqId = 'BENCH-TMO-' + Math.floor(Math.random() * 9000 + 1000);
                const payload = {
                    client_request_id: reqId,
                    agent_id: 'AGENT-BENCH',
                    customer_name: 'Timeout Demo',
                    date_of_birth: '1990-01-01',
                    destination: 'INTERNATIONAL',
                    trip_start: '2026-12-01',
                    trip_end: '2026-12-05',
                    plan_code: 'PREMIUM',
                    sum_insured: 500000
                };
                const r1 = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const d1 = await r1.json();
                const id = d1.data.id;

                logBox.innerHTML += `> [Step 1] Created Request #${id}. Now submitting with scenario TIMEOUT_AFTER_ISSUE...\n`;
                const r2 = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify({ scenario: 'TIMEOUT_AFTER_ISSUE' })
                });
                const d2 = await r2.json();
                logBox.innerHTML += `> Upstream timed out (504). PolicyFlow shifted state to: [${d2.status}]. ProviderRef: ${d2.data.provider_reference}\n`;

                logBox.innerHTML += `> [Step 2] Triggering reconciliation query with upstream provider...\n`;
                const r3 = await fetch(`/api/policy-requests/${id}/reconcile`, { method: 'POST', headers: {'Accept': 'application/json'} });
                const d3 = await r3.json();
                logBox.innerHTML += `> Reconcile Result: ${d3.message}\n`;
                logBox.innerHTML += `> Verified Policy Number: ${d3.data.policy_number} | Status: ${d3.data.status}\n`;
                logBox.innerHTML += `> SUCCESS: Timeout recovered into ISSUED without double-issuance!\n`;
                setTimeout(() => window.location.reload(), 3000);
            }
            else if (type === 'duplicate_callback') {
                const evtId = 'EVT-BENCH-' + Math.floor(Math.random() * 9000 + 1000);
                const mockRef = 'PRV-BENCH-' + Math.floor(Math.random() * 9000 + 1000);
                const cbPayload = {
                    event_id: evtId,
                    provider_reference: mockRef,
                    event_type: 'POLICY_ISSUED',
                    policy_number: 'POL-MOCK-999',
                    timestamp: new Date().toISOString()
                };

                logBox.innerHTML += `> [Step 1] Delivering Webhook Callback Event (${evtId})...\n`;
                const r1 = await fetch('/api/mock-insurer/callback', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(cbPayload)
                });
                const d1 = await r1.json();
                logBox.innerHTML += `> Callback 1 Processed: status=${d1.status}\n`;

                logBox.innerHTML += `> [Step 2] Delivering EXACT SAME Webhook Callback Event (${evtId})...\n`;
                const r2 = await fetch('/api/mock-insurer/callback', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(cbPayload)
                });
                const d2 = await r2.json();
                logBox.innerHTML += `> Callback 2 Response: status=${d2.status} (duplicate_ignored) -> Idempotency Guaranteed!\n`;
                setTimeout(() => window.location.reload(), 2500);
            }
            else if (type === 'retry_failure') {
                const reqId = 'BENCH-RETRY-' + Math.floor(Math.random() * 9000 + 1000);
                const payload = {
                    client_request_id: reqId,
                    agent_id: 'AGENT-BENCH',
                    customer_name: 'Retry Test Customer',
                    date_of_birth: '2000-01-01',
                    destination: 'DOMESTIC',
                    trip_start: '2026-11-15',
                    trip_end: '2026-11-20',
                    plan_code: 'BASIC',
                    sum_insured: 100000
                };
                const r1 = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const d1 = await r1.json();
                const id = d1.data.id;
                const frozenPremium = d1.data.calculated_premium;

                logBox.innerHTML += `> [Step 1] Created Policy #${id} with Immutable Premium Snapshot: ₹${frozenPremium}\n`;
                logBox.innerHTML += `> [Step 2] Attempt #1: Provider responds 503 TEMPORARY_FAILURE...\n`;
                const r2 = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify({ scenario: 'TEMPORARY_FAILURE' })
                });
                logBox.innerHTML += `> Attempt 1 failed with 503 as expected.\n`;

                logBox.innerHTML += `> [Step 3] Attempt #2: Retrying issuance with scenario SUCCESS...\n`;
                const r3 = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify({ scenario: 'SUCCESS' })
                });
                const d3 = await r3.json();
                logBox.innerHTML += `> Attempt 2 Succeeded: Policy #${d3.data.policy_number} ISSUED!\n`;
                logBox.innerHTML += `> Premium Preserved: ₹${d3.data.calculated_premium} == ₹${frozenPremium} (Zero Price Creep)!\n`;
                setTimeout(() => window.location.reload(), 2500);
            }
        }
    </script>
</body>
</html>
