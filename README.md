# PolicyFlow: Fault-Tolerant Insurance Engine

Campus Placement — Software Engineering Practical Assessment  
**Author:** Candidate Submission  
**Stack:** PHP 8.3 / Laravel 11 / SQLite & MySQL / Tailwind CSS  

---

## 1. What I Built

PolicyFlow is an insurance intake, pricing, and issuance service built to handle the messy reality of external third-party APIs. 

In travel insurance, distributor systems receive purchase requests from agents and have to issue them through external insurer systems. But external insurer APIs often fail in complex ways:
- Network calls time out after the insurer has already processed the payment or created the policy.
- Webhooks arrive multiple times due to at-least-once delivery semantics.
- Clients click submit multiple times or retry aggressively under poor connectivity.

If the application isn't designed defensively, these issues lead to duplicate policy numbers, double charges, and corrupted state. PolicyFlow addresses this by enforcing **strict database-level idempotency**, a **deterministic pricing engine using decimal-safe arithmetic**, an **explicit unidirectional state machine**, and **reconciliation workflows** to resolve ambiguous states.

---

## 2. Core Architecture & Design Choices

```
[ Travel Agent / Client ]
           │
           ▼
[ POST /api/policy-requests ]
           │
           ├─ 1. Form Validation (dates, plan codes, sum insured)
           ├─ 2. PricingEngine (bcmath: Rate × Days × Multipliers)
           ├─ 3. DB Compound Unique Index [agent_id, client_request_id]
           │     └─ If already exists ➔ return HTTP 200 with existing record
           │     └─ If new ➔ insert record, state = VALIDATED (HTTP 201)
           ▼
[ POST /api/policy-requests/{id}/submit ]
           │
           ├─ 1. DB Row Lock (lockForUpdate)
           ├─ 2. Guard: if status == ISSUED ➔ short-circuit, do not call insurer
           ├─ 3. State transition: VALIDATED ➔ SUBMITTED
           ├─ 4. Dispatch to upstream Mock Insurer
           │
           ├── Upstream returns 200 (SUCCESS)
           │     └─ Save policy_number, transition to ISSUED
           │
           ├── Upstream returns 504 / Connection Timeout
           │     └─ Transition to NEEDS_RECONCILIATION (Never blindly reissue!)
           │
           ├── Upstream returns 202 (DELAYED_CALLBACK)
           │     └─ Transition to PENDING_CONFIRMATION, await webhook
           │
           └── Upstream returns 503 (TEMPORARY_FAILURE)
                 └─ Increment attempt_count, allow safe retry with frozen pricing
```

### Key Engineering Decisions:

1. **Database-Level Uniqueness Over Application-Only Checks:**
   Checking `where('client_request_id', ...)->first()` in PHP is not enough under high concurrency. If two identical requests hit the server within the same millisecond, both will pass the check and create duplicates. I added a unique constraint on `['agent_id', 'client_request_id']` at the database level and wrapped insertion in a transaction catching `UniqueConstraintViolationException`.
2. **Decimal-Safe Math (`bcmath`):**
   Standard PHP floating-point numbers (`$a * $b`) suffer from binary IEEE 754 precision issues (e.g., `0.1 + 0.2 = 0.30000000000000004`). In insurance and fintech, small rounding errors cause audit failures. I implemented the pricing engine using PHP's `bcmath` extension (`bcmul`, scale 4, rounded to 2 decimals).
3. **Pessimistic Locking for State Transitions:**
   Whenever a policy is submitted, retried, or reconciled, the row is locked with `lockForUpdate()`. This stops race conditions between incoming webhooks and client retries.
4. **Terminal `ISSUED` State:**
   Once a policy reaches `ISSUED`, it can never transition back to `SUBMITTED`, `PENDING`, or `FAILED`. Repeated calls simply return the existing confirmed policy.

---

## 3. Implemented Reliability Scenarios

I implemented all four reliability scenarios outlined in the prompt:

### Case 1: Duplicate Intake
- **Scenario:** The same `agent_id` and `client_request_id` are submitted repeatedly, either sequentially or concurrently.
- **Handling:** The first submission returns `HTTP 201 Created`. Subsequent submissions return `HTTP 200 OK` with `is_duplicate: true` and the existing policy data. No secondary logical row is created in the database.
- **Tested in:** `tests/Feature/PolicyIntakeTest.php` (`test_repeated_agent_and_client_request_id_does_not_create_another_logical_request`)

### Case 2: Timeout After Success (`TIMEOUT_AFTER_ISSUE`)
- **Scenario:** The mock insurer successfully creates the policy internally, but the HTTP connection drops or times out before returning to us.
- **Handling:** Instead of marking the request failed or blindly creating another policy, the system transitions the policy to `NEEDS_RECONCILIATION`. When `php artisan policyflow:reconcile` (or `POST /reconcile`) runs, it queries the provider's status endpoint, matches the existing record, and safely moves the policy to `ISSUED` with the insurer's policy number.
- **Tested in:** `tests/Feature/ReliabilityScenariosTest.php` (`test_reliability_case_2_timeout_after_success_and_reconciliation`)

### Case 3: Duplicate Callback Handling
- **Scenario:** The insurer delivers the same webhook notification 2 or 3 times.
- **Handling:** Incoming events are logged in the `provider_events` table with a unique `event_id`. The first event processes normally; subsequent deliveries are flagged as `duplicate_ignored`. If a webhook arrives for an already `ISSUED` policy, the state remains protected.
- **Tested in:** `tests/Feature/ReliabilityScenariosTest.php` (`test_reliability_case_3_duplicate_callback_idempotency`)

### Case 4: Retry After Temporary Failure (`TEMPORARY_FAILURE` / 503)
- **Scenario:** Upstream provider returns a 503 error on the first attempt.
- **Handling:** The failure is recorded in `issuance_attempts`, and `attempt_count` increments. When retried, the policy uses its original immutable calculated premium snapshot (preventing price creep). If it succeeds, exactly one policy number is issued.
- **Tested in:** `tests/Feature/ReliabilityScenariosTest.php` (`test_reliability_case_4_retry_after_temporary_failure`)

---

## 4. Pricing Rules & Verification

Formula used:
$$\text{Premium} = \text{base\_daily\_rate} \times \text{travel\_days} \times \text{age\_multiplier} \times \text{destination\_multiplier} \times \text{coverage\_multiplier}$$

- **Base daily rate:** `BASIC` = ₹12.00, `PLUS` = ₹20.00, `PREMIUM` = ₹30.00
- **Travel days:** Inclusive (`trip_end - trip_start + 1`), minimum 1 day.
- **Age multiplier (on trip start):**
  - 0–17: 1.10
  - 18–60: 1.00
  - 61–70: 1.50
  - 71+: 2.00
- **Destination multiplier:** `DOMESTIC` = 1.00, `INTERNATIONAL` = 1.80
- **Coverage multiplier:** ₹100,000 = 1.00, ₹300,000 = 1.25, ₹500,000 = 1.50

**Verification with prompt sample:**
- Customer: Sample Customer (DOB: 1998-04-10)
- Trip: 2026-11-10 to 2026-11-14 (5 days)
- Destination: `INTERNATIONAL` (1.80), Plan: `PLUS` (₹20), Coverage: ₹300,000 (1.25)
- Age at trip start: 28 years (multiplier 1.00)
- Calculation: $20 \times 5 \times 1.00 \times 1.80 \times 1.25 = \mathbf{₹225.00}$ (matches requirement).

---

## 5. Local Setup & Running Instructions

### Requirements:
- PHP 8.2 or 8.3 (with `bcmath`, `pdo_sqlite` or `pdo_mysql`, `curl`, `mbstring`)
- Composer

### Setup Steps:

1. **Clone the repo and navigate to directory:**
   ```bash
   cd policyflow
   ```

2. **Install Composer dependencies:**
   ```bash
   composer install
   ```

3. **Set up the environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Prepare the database:**
   By default, it uses SQLite with zero external dependencies:
   ```bash
   touch database/database.sqlite
   php artisan migrate:fresh --seed
   ```
   *(To use MySQL instead, set `DB_CONNECTION=mysql` and your credentials in `.env`)*

5. **Run the automated tests:**
   ```bash
   php artisan test
   ```
   All 21 unit and feature tests will run against an in-memory database.

6. **Start the local server:**
   ```bash
   php artisan serve
   ```
   Open **`http://127.0.0.1:8000`** in your browser.

---

## 6. Testing via Dashboard or cURL

### Interactive Web Dashboard (`http://127.0.0.1:8000`):
I included a lightweight UI built with Blade and Tailwind CSS. At the top of the page is a **Reliability Test Bench** with 4 buttons:
- Click **"1. Duplicate Intake"** to see duplicate handling in the console.
- Click **"2. Timeout & Reconciliation"** to watch a 504 timeout transition to `NEEDS_RECONCILIATION` and then resolve to `ISSUED`.
- Click **"3. Duplicate Callback"** to test webhook deduplication.
- Click **"4. Retries & Pricing Freeze"** to test a 503 retry without price change.

### Testing via cURL:
An automated script with all endpoints is in `examples/curl_commands.sh`:
```bash
bash examples/curl_commands.sh
```

Or run manual requests:
```bash
# 1. Intake a new policy:
curl -X POST http://127.0.0.1:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'

# 2. Submit issuance:
curl -X POST http://127.0.0.1:8000/api/policy-requests/1/submit \
  -H "Content-Type: application/json" \
  -d '{"scenario": "SUCCESS"}'

# 3. Run reconciliation command:
php artisan policyflow:reconcile
```

---

## 7. Known Assumptions & Trade-offs

1. **Synchronous REST by Default:** In production, issuance submission would ideally be queued on Redis with exponential backoff workers. For this assignment, I prioritized deterministic synchronous flows while providing the CLI command `policyflow:reconcile` for batch resolution.
2. **Mock Insurer Storage:** The mock insurer stores state in `mock_insurer_records` in the same database to allow realistic status lookups during reconciliation without needing a separate mock microservice running.
3. **Single Currency:** Calculations assume INR (₹). Currency exchange rates were not in scope.
