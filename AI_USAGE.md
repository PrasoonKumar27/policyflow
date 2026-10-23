# AI Usage & Verification Disclosure

In adherence to Section 10 (AI Usage Policy) of the Software Engineering Practical Assessment, this document outlines the usage of AI-assisted tools during the development of **PolicyFlow**.

---

## 1. Tools Used
- **Claude / Gemini / Antigravity AI Assistant**: Used for boilerplate structuring, test scaffolding, state machine edge-case analysis, and documentation generation.
- **PHP 8.3 & PHPUnit CLI**: Used to execute, test, and independently verify the behavior of all code.

---

## 2. Concrete Ways AI Assisted

1. **State Machine Transition Matrix & Edge Case Modeling:**
   - Assisted in formalizing the state transition graph for policy lifecycle:
     `RECEIVED ➔ VALIDATED ➔ SUBMITTED ➔ (PENDING_CONFIRMATION | NEEDS_RECONCILIATION | ISSUED | FAILED)`.
   - Identified that `ISSUED` must be strictly terminal, ensuring no subsequent webhook or retry can regress the policy back to `SUBMITTED` or `FAILED`.

2. **Decimal-Safe Financial Calculation:**
   - Guided the implementation of the `PricingEngine` using PHP's `bcmath` extension (`bcmul`, scale 4, formatted to 2 decimals) to prevent binary floating-point inaccuracies that frequently plague financial systems.

3. **Reliability Scenario Test Scaffolding:**
   - Generated the initial test suite fixtures covering all four fault scenarios: duplicate intake, timeout after success with upstream reconciliation, duplicate asynchronous webhook callbacks, and temporary upstream failures with safe retries.

---

## 3. Incorrect or Incomplete Suggestion Encountered

### The Issue:
During the initial implementation of `IssuanceService`, the AI drafted the `submit()` and `reconcile()` methods with the following call:
```php
$this->stateMachine->transition(
    $request,
    PolicyRequest::STATUS_ISSUED,
    "Policy issued..."
);
return $request;
```
Inside `PolicyStateMachine::transition()`, a database transaction with `lockForUpdate()` is used, which correctly updated the row in the database and returned the updated model instance. However, `$request` in `IssuanceService` was not reassigned to this return value or refreshed in-memory. As a result, the in-memory object still had its previous status (`SUBMITTED` or `NEEDS_RECONCILIATION`), causing the HTTP controller to return the old status even though the database was updated!

### How It Was Caught and Fixed:
The automated test `test_reliability_case_2_timeout_after_success_and_reconciliation` failed asserting that `'data.status'` was `'ISSUED'`.
We identified the discrepancy between the persisted database record and the in-memory model, and corrected it across `IssuanceService` by reassigning:
```php
$request = $this->stateMachine->transition($request, ...);
```
All 21 test cases (105 assertions) immediately passed.

---

## 4. Verification & Testing Methodology
- **Automated Tests:** 21 automated unit and feature tests were executed with PHPUnit against SQLite in-memory and file databases (`php artisan test`).
- **Interactive UI Testing:** Evaluated all 4 reliability scenarios interactively on the local browser dashboard (`http://127.0.0.1:8000`).
- **cURL Integration Verification:** Tested HTTP responses, headers, and status codes (`201`, `200`, `422`, `408`, `502`) using `examples/curl_commands.sh`.
- **Static Analysis & Linting:** Code reviewed for type safety, transaction boundaries, and absence of hardcoded credentials or private data.
