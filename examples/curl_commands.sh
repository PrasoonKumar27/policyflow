#!/usr/bin/env bash
# ==============================================================================
# PolicyFlow API Demonstration & Testing Script
# Run locally with: bash examples/curl_commands.sh
# Ensure: php artisan serve is running at http://127.0.0.1:8000
# ==============================================================================

BASE_URL="http://127.0.0.1:8000/api"

echo "=========================================================="
echo " 1. POLICY INTAKE: Create a New Policy Request"
echo "=========================================================="
curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-9001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | json_pp 2>/dev/null || cat
echo -e "\n"

echo "=========================================================="
echo " 2. IDEMPOTENT INTAKE: Re-submitting Identical Request"
echo "    Expects HTTP 200 with is_duplicate: true"
echo "=========================================================="
curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-9001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | json_pp 2>/dev/null || cat
echo -e "\n"

echo "=========================================================="
echo " 3. LIST POLICY REQUESTS with Status Filter"
echo "=========================================================="
curl -s -X GET "$BASE_URL/policy-requests?status=VALIDATED" \
  -H "Accept: application/json" | json_pp 2>/dev/null || cat
echo -e "\n"

echo "=========================================================="
echo " 4. SUBMIT ISSUANCE: Standard SUCCESS Scenario"
echo "=========================================================="
curl -s -X POST "$BASE_URL/policy-requests/AGT-2026-9001/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "SUCCESS"}' | json_pp 2>/dev/null || cat
echo -e "\n"

echo "=========================================================="
echo " 5. RETRIEVE POLICY DETAILS & AUDIT TRAIL"
echo "=========================================================="
curl -s -X GET "$BASE_URL/policy-requests/AGT-2026-9001" \
  -H "Accept: application/json" | json_pp 2>/dev/null || cat
echo -e "\n"

echo "=========================================================="
echo " 6. RELIABILITY DEMO: Timeout After Success & Reconciliation"
echo "=========================================================="
# Step A: Create Request
curl -s -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-TMO-DEMO",
    "agent_id": "AGENT-17",
    "customer_name": "Timeout Demo User",
    "date_of_birth": "1990-01-01",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-20",
    "trip_end": "2026-11-25",
    "plan_code": "PREMIUM",
    "sum_insured": 500000
  }' > /dev/null

# Step B: Submit with TIMEOUT_AFTER_ISSUE
echo "Submitting with upstream simulated 504 Timeout..."
curl -s -X POST "$BASE_URL/policy-requests/AGT-TMO-DEMO/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "TIMEOUT_AFTER_ISSUE"}' | json_pp 2>/dev/null || cat
echo -e "\n"

# Step C: Reconcile
echo "Reconciling with Upstream Provider Status Endpoint..."
curl -s -X POST "$BASE_URL/policy-requests/AGT-TMO-DEMO/reconcile" \
  -H "Accept: application/json" | json_pp 2>/dev/null || cat
echo -e "\n"

echo "=========================================================="
echo " 7. RELIABILITY DEMO: Duplicate Provider Callback Webhook"
echo "=========================================================="
# First delivery:
echo "Delivery 1 of Webhook Event EVT-DEMO-001:"
curl -s -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "event_id": "EVT-DEMO-001",
    "provider_reference": "PRV-DEMO-XYZ",
    "event_type": "POLICY_ISSUED",
    "policy_number": "POL-ASYNC-999"
  }' | json_pp 2>/dev/null || cat
echo -e "\n"

# Second delivery (Duplicate):
echo "Delivery 2 of SAME Webhook Event EVT-DEMO-001:"
curl -s -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "event_id": "EVT-DEMO-001",
    "provider_reference": "PRV-DEMO-XYZ",
    "event_type": "POLICY_ISSUED",
    "policy_number": "POL-ASYNC-999"
  }' | json_pp 2>/dev/null || cat
echo -e "\n"

echo "Demonstration complete."
