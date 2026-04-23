#!/usr/bin/env bash
# Quick Apache Bench smoke test for the product listing endpoint.
# Requires: `ab` (apache2-utils / httpd).
#
# Usage:
#   ./stress-tests/ab-benchmark.sh
set -euo pipefail

BASE="${BASE:-http://localhost:8000}"
REQUESTS="${REQUESTS:-1000}"
CONCURRENCY="${CONCURRENCY:-50}"

echo "=== Warm-up ==="
curl -s -o /dev/null "${BASE}/api/v1/products"

echo
echo "=== GET /api/v1/products (warm cache) ==="
ab -n "${REQUESTS}" -c "${CONCURRENCY}" -H 'Accept: application/json' "${BASE}/api/v1/products" | grep -E "Requests per second|Time per request|Failed requests"

echo
echo "Hint: compare with STOCK_STRATEGY=pessimistic vs STOCK_STRATEGY=optimistic"
echo "      and with CACHE_STORE=redis vs CACHE_STORE=file"
