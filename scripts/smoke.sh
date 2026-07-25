#!/usr/bin/env bash
#
# End-to-end smoke test for the auth API. Exercises the real HTTP path a
# freshly bootstrapped service exposes — the path unit tests cannot vouch for
# because they create the Passport client themselves. Requires curl and jq.
#
# Usage: BASE_URL=http://127.0.0.1:8000 scripts/smoke.sh
set -euo pipefail

BASE="${BASE_URL:-http://127.0.0.1:8000}/api/v1"
EMAIL="smoke+$(date +%s)@example.com"
PASS="password123"

echo "→ register ($EMAIL)"
register=$(curl -fsS -X POST "$BASE/register" \
    -H 'Accept: application/json' \
    --data-urlencode "name=Smoke Test" \
    --data-urlencode "email=${EMAIL}" \
    --data-urlencode "password=${PASS}" \
    --data-urlencode "password_confirmation=${PASS}")
token=$(echo "$register" | jq -er '.data.access_token')

echo "→ profile with registration token"
curl -fsS "$BASE/user" \
    -H 'Accept: application/json' \
    -H "Authorization: Bearer ${token}" | jq -e '.data.email' >/dev/null

echo "→ login"
login=$(curl -fsS -X POST "$BASE/login" \
    -H 'Accept: application/json' \
    --data-urlencode "email=${EMAIL}" \
    --data-urlencode "password=${PASS}")
login_token=$(echo "$login" | jq -er '.data.access_token')

echo "→ logout"
curl -fsS -X POST "$BASE/logout" \
    -H 'Accept: application/json' \
    -H "Authorization: Bearer ${login_token}" | jq -e '.message' >/dev/null

echo "→ verify token is revoked (expect 401)"
code=$(curl -sS -o /dev/null -w '%{http_code}' "$BASE/user" \
    -H 'Accept: application/json' \
    -H "Authorization: Bearer ${login_token}")
if [ "$code" != "401" ]; then
    echo "✗ expected 401 after logout, got ${code}" >&2
    exit 1
fi

echo "✓ smoke test passed"
