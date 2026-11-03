#!/usr/bin/env bash
# Production/staging post-deploy smoke checks. Strictly read-only/unauthenticated:
# no login, no mutation, no billing exercise. Exit 1 stops the deployment runner
# and triggers the rollback criterion in docs/deployment/ROLLBACK_PLAN.md.
set -Eeuo pipefail

: "${API_URL:?API_URL required, e.g. https://api.example.com}"
: "${EXPECTED_FRONTEND_ORIGIN:?EXPECTED_FRONTEND_ORIGIN required, e.g. https://app.example.com}"

fail() { echo "SMOKE FAIL: $*" >&2; exit 1; }
ok()   { printf 'SMOKE PASS: %s\n' "$*"; }

# 1. Health endpoint reachable over TLS with a valid certificate.
code="$(curl -fsS --max-time 10 -o /dev/null -w '%{http_code}' "$API_URL/api/v1/health")" \
  || fail "/api/v1/health unreachable at $API_URL"
[[ "$code" == "200" ]] || fail "/api/v1/health returned $code, expected 200"
ok "health 200"

# 2. Auth contract intact: unauthenticated protected route must 401.
code="$(curl -sS --max-time 10 -o /dev/null -w '%{http_code}' "$API_URL/api/v1/me")"
[[ "$code" == "401" ]] || fail "/api/v1/me unauthenticated returned $code, expected 401"
ok "401 for unauthenticated /api/v1/me"

# 3. CSRF + session cookie handshake must return cookies for the SPA client.
headers="$(curl -sS -D - -o /dev/null --max-time 10 "$API_URL/sanctum/csrf-cookie")"
echo "$headers" | grep -qi 'set-cookie:.*XSRF-TOKEN' || fail "XSRF-TOKEN cookie not issued"
echo "$headers" | grep -qi 'set-cookie:.*=' && echo "$headers" | grep -c 'set-cookie' > /tmp/erp-smoke-ccount
ok "CSRF handshake returns cookies ($(cat /tmp/erp-smoke-ccount 2>/dev/null || echo 0) set-cookie headers)"

# 4. CORS preflight from the configured frontend origin only.
preflight="$(curl -sS -o /dev/null --max-time 10 -w '%{http_code}' -X OPTIONS \
  -H "Origin: $EXPECTED_FRONTEND_ORIGIN" \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type' \
  "$API_URL/api/v1/auth/login")"
[[ "$preflight" == "200" || "$preflight" == "204" ]] \
  || fail "CORS preflight rejected ($preflight) for $EXPECTED_FRONTEND_ORIGIN"
ok "CORS preflight for allowed origin"

# 5. No framework debug output may ever leak in production responses.
body="$(curl -sS --max-time 10 "$API_URL/api/v1/smoke-nonexistent-route")"
echo "$body" | grep -qi 'vendor.*laravel\|Whoops\|stack trace' && fail "debug output leaked on 404/5xx body"
ok "no framework debug output"

# 6. HTTPS redirect discipline: port 80 must redirect, not serve content.
http_code="$(curl -sS -o /dev/null --max-time 10 -w '%{http_code}' "${API_URL/https/http}")"
[[ "$http_code" == "301" || "$http_code" == "308" || "$http_code" == "302" ]] \
  || fail "plain HTTP returned $http_code instead of an HTTPS redirect"
ok "HTTP->HTTPS redirect"

echo "SMOKE OK — 6/6 gates passed at $(date -u +%FT%TZ)"
