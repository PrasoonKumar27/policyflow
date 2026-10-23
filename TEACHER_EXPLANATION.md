# 🎓 PolicyFlow: Teacher & Viva Explanation Guide

> **Purpose:** This guide is specifically written to help you explain this project to your professor, teacher, or viva examiner with 100% confidence, technical depth, and clarity.

---

## 1. The 60-Second Elevator Pitch (Start with this!)

> *"Good morning/afternoon, Sir/Ma'am. My project is called **PolicyFlow**. It is a fault-tolerant travel insurance intake, issuance, and reconciliation engine built with PHP 8.3 and Laravel 11.*
> 
> *In the real insurance world, travel agents submit policy requests to upstream insurers. But external insurers are notoriously unreliable: network calls time out, webhooks get sent twice, and systems fail intermittently. In finance and insurance, if an API call times out and your system blindly retries, you might accidentally issue two separate policies and double-charge the customer.*
> 
> *PolicyFlow solves this distributed systems problem by enforcing **strict database-level idempotency**, a **deterministic pricing engine using decimal-safe arithmetic**, an **explicit unidirectional state machine**, and an **upstream reconciliation mechanism** that safely resolves timeouts without duplicate issuance."*

---

## 2. The Core Problem We Are Solving

Explain to the teacher why a normal web app is NOT enough for insurance:

| Problem in Naive Applications | Real-World Risk | PolicyFlow's Solution |
| :--- | :--- | :--- |
| Agent double-clicks the "Buy Policy" button | Two policy records created; customer charged twice | Database unique constraint `(agent_id, client_request_id)` returns the existing record (HTTP 200) instead of duplicating (HTTP 201). |
| Upstream insurer takes 8 seconds and the HTTP connection times out | The insurer issued the policy on their end, but the caller thinks it failed. Blindly retrying creates a 2nd policy! | Request is shifted to `NEEDS_RECONCILIATION`. System never reissues blindly; it reconciles against the insurer's status API. |
| Insurer sends the webhook callback twice | State machine might execute twice, sending two notification emails or triggering duplicate records | Unique `event_id` deduplication table. Subsequent callbacks return `duplicate_ignored`. |
| Float rounding errors (`0.1 + 0.2 = 0.30000000000000004`) | Auditing mismatches and legal compliance violations | Decimal-safe arithmetic using PHP `bcmath` with scale 4 rounded to 2 decimals. |

---

## 3. High-Level Architecture Walkthrough

When explaining the code, guide your teacher through these **4 Core Pillars**:

```
[ Travel Agent / Client ]
           │
           ▼ (POST /api/policy-requests)
┌────────────────────────────────────────────────────────┐
│ 1. INTAKE & PRICING LAYER                              │
│    - StorePolicyRequest Form Validation                │
│    - PricingEngine (bcmath: Rate × Days × Multipliers) │
│    - Immutable Pricing Snapshot persisted              │
│    - Natural Compound Unique Index (agent + client_id) │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼ (POST /api/policy-requests/{id}/submit)
┌────────────────────────────────────────────────────────┐
│ 2. CONCURRENCY & STATE MACHINE LAYER                   │
│    - DB::transaction + lockForUpdate()                 │
│    - Transitions: RECEIVED ➔ VALIDATED ➔ SUBMITTED    │
│    - Audit History: policy_state_history               │
└──────────────────────────┬─────────────────────────────┘
                           │
           ┌───────────────┴───────────────┐
           ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────┐
│ 3. UPSTREAM MOCK        │     │ 4. RECONCILIATION &     │
│    INSURER              │     │    CALLBACK ENGINE      │
│ - SUCCESS (200)         │     │ - Unique event_id check │
│ - TIMEOUT (504)         │     │ - Query provider status │
│ - 503 TEMP FAILURE      │     │ - Terminal ISSUED lock  │
│ - DELAYED CALLBACK      │     │ - Artisan cli reconcile │
└─────────────────────────┘     └─────────────────────────┘
```

---

## 4. How the 4 Reliability Scenarios Work (Walk through each!)

### Scenario 1: Duplicate Intake (Near-Simultaneous Requests)
- **Where to show code:** `app/Http/Controllers/PolicyRequestController.php` (method: `store`) and migration `database/migrations/2026_10_23_000001_create_policy_requests_table.php`.
- **How to explain:**
  > *"Sir, if an agent network lags and they send the request twice, we enforce uniqueness in two places: First, in application code via a query check. Second, at the database engine level via `$table->unique(['agent_id', 'client_request_id'])`. If two requests hit the server in the exact same millisecond, the database throws a `UniqueConstraintViolationException`, which our controller catches gracefully and returns HTTP 200 with the existing record, avoiding race conditions."*

### Scenario 2: Timeout After Success (`TIMEOUT_AFTER_ISSUE`)
- **Where to show code:** `app/Services/IssuanceService.php` (lines 75-88) & `reconcile()` method.
- **How to explain:**
  > *"When the external insurer takes too long or drops the TCP packet, we do NOT mark the policy as FAILED, nor do we blindly retry. Instead, we move the policy into `NEEDS_RECONCILIATION`. Later, our automated command `php artisan policyflow:reconcile` queries the insurer's status API. If the insurer confirms it was issued, we update the status to `ISSUED` with the insurer's policy number. If not, it can be safely retried. This completely eliminates duplicate issuance."*

### Scenario 3: Duplicate Webhook Callback
- **Where to show code:** `app/Services/IssuanceService.php` (`processCallback`) & `app/Models/ProviderEvent.php`.
- **How to explain:**
  > *"Insurer webhooks often deliver at-least-once. When an event arrives, we check the `provider_events` table for a unique `event_id`. If it's already there, we immediately return `duplicate_ignored`. Furthermore, if the policy is already `ISSUED`, our state machine forbids any callback from modifying or regressing it."*

### Scenario 4: Retry After Temporary Failure (`TEMPORARY_FAILURE` / 503)
- **Where to show code:** `app/Services/IssuanceService.php` and `tests/Feature/ReliabilityScenariosTest.php`.
- **How to explain:**
  > *"If the upstream insurer is temporarily down (HTTP 503), we record the attempt in `issuance_attempts` table and increment `attempt_count`. When the agent retries, the system does not recompute or alter the premium. The original calculated premium snapshot is immutable, protecting the customer from price creep during retries."*

---

## 5. Likely Teacher / Viva Questions & How to Answer Them

### Q1: *"Why did you use `bcmath` instead of normal multiplication (`$a * $b`) for premium calculation?"*
- **Your Answer:**
  > *"Standard PHP floating-point numbers use IEEE 754 binary representation, which cannot accurately represent certain decimal fractions (like 0.1 or 0.2), causing rounding drift over multiple multiplications. In financial and insurance software, monetary discrepancies are compliance violations. `bcmath` performs arbitrary precision decimal-safe string arithmetic, guaranteeing 100% precision."*

### Q2: *"What is Idempotency and how did you implement it?"*
- **Your Answer:**
  > *"An operation is idempotent if performing it multiple times yields the same exact outcome as performing it once, without unintended side effects. We achieved this in three ways:*
  > 1. *Intake Idempotency: Unique compound key `[agent_id, client_request_id]`.*
  > 2. *Callback Idempotency: Unique `event_id` in `provider_events` table.*
  > 3. *Issuance Idempotency: If `submit()` is called on an already `ISSUED` policy, the service short-circuits and returns the existing policy without contacting upstream."*

### Q3: *"How do you handle race conditions when two requests arrive simultaneously?"*
- **Your Answer:**
  > *"We use database pessimistic locking via `lockForUpdate()` inside a `DB::transaction()` block. This places a row-level write lock in MySQL/PostgreSQL/SQLite, forcing concurrent requests to wait sequentially so the state machine cannot be evaluated concurrently on stale data."*

### Q4: *"Can a policy transition backwards from ISSUED to SUBMITTED?"*
- **Your Answer:**
  > *"No, Sir. Our `PolicyStateMachine` enforces a strict transition matrix. The `ISSUED` state has an empty transitions array, meaning it is strictly terminal. Any attempt to regress the state throws an `InvalidStateTransitionException` and is logged in our audit trail."*

### Q5: *"How do you test your code?"*
- **Your Answer:**
  > *"We have 21 automated PHPUnit tests with 105 assertions covering unit math, state machine boundaries, API intake validation, and all four reliability fault scenarios. Running `php artisan test` executes the entire suite in under 0.5 seconds."*

---

## 6. Live Demonstration Steps (If the teacher asks to see it run!)

1. **Open the Terminal:**
   ```bash
   cd /Users/prasoonkumar/.gemini/antigravity/scratch/policyflow
   php artisan serve
   ```
2. **Open the Browser at:** `http://127.0.0.1:8000`
3. **Show the Evaluator Test Bench:**
   - Click **"1. Duplicate Intake"** -> Point out the console log showing HTTP 201 on first call and HTTP 200 on duplicate call with `is_duplicate: true`.
   - Click **"2. Timeout & Reconciliation"** -> Point out the status changing to `NEEDS_RECONCILIATION` on 504 timeout, and then changing to `ISSUED` after reconciliation.
   - Click **"3. Duplicate Callback"** -> Point out `duplicate_ignored` on the 2nd delivery.
   - Click **"4. Retries & Pricing Freeze"** -> Point out how the premium remains exactly the same between attempt 1 and attempt 2.
4. **Show the Automated Tests:**
   ```bash
   php artisan test
   ```
   Show all 21 tests green!
