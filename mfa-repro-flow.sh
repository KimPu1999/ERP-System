#!/usr/bin/env bash
# Reproduction of the Platform Super Admin MFA enrollment flow exactly as the SPA performs it:
# csrf cookie (decoded like api.ts) -> platform login (202) -> /auth/me -> enroll -> confirm
set -Eeuo pipefail
API=http://localhost:8001
JAR=/tmp/erp-mfa-repro.jar
rm -f "$JAR"
step() { printf '\n===== %s =====\n' "$1"; }
httpcode() { sed -n 's/^HTTP\/1\.[01] //p' | head -1; }

step "1. CSRF cookie"
curl -sS -o /dev/null -c "$JAR" "$API/sanctum/csrf-cookie"
RAW=$(grep -v '^#' "$JAR" | grep 'XSRF-TOKEN' | awk '{print $7}')
CSRF=$(python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1]))" "$RAW")

step "2. Platform login (expect 202 mfa_enrollment_required)"
LOGIN_CODE=$(curl -sS -b "$JAR" -c "$JAR" -H "X-XSRF-TOKEN: $CSRF" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"mfa-repro@example.test","password":"repro-password-1"}' -w '\n%{http_code}' "$API/api/v1/auth/platform/login")
echo "$LOGIN_CODE"
echo "$LOGIN_CODE" | tail -1 | grep -q 202 || { echo "LOGIN DID NOT RETURN 202 — aborting"; exit 1; }

step "3. /auth/me (SPA session probe)"
curl -sS -o /dev/null -w 'me status: %{http_code}\n' -b "$JAR" -c "$JAR" -H "Accept: application/json" "$API/api/v1/auth/me"

step "4. /auth/mfa/enroll (captures secret)"
ENROLL=$(curl -sS -b "$JAR" -c "$JAR" -H "X-XSRF-TOKEN: $CSRF" -H "Content-Type: application/json" -H "Accept: application/json" \
  -X POST "$API/api/v1/auth/mfa/enroll" -w '\n%{http_code}')
echo "$ENROLL" | grep -v "secret\|otpauth" || true
echo "(secret redacted from output)"

step "5. Compute TOTP and confirm (expect 200 recovery_codes)"
SECRET=$(echo "$ENROLL" | head -1 | python3 -c "import sys,json; print(json.load(sys.stdin)['secret'])")
CODE=$(DB_DATABASE=erp_test php artisan tinker --execute="echo app(App\Services\MfaService::class)->totp('$SECRET', (int) floor(time()/30));" 2>/dev/null | tail -1)
echo "totp computed (not printed)"
CONFIRM=$(curl -sS -b "$JAR" -c "$JAR" -H "X-XSRF-TOKEN: $CSRF" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"code":"'$CODE'"}' -X POST "$API/api/v1/auth/mfa/confirm" -w '\n%{http_code}')
echo "(recovery codes redacted)"
echo "confirm status line:"; echo "$CONFIRM" | tail -1

step "6. Post-confirmation /auth/me"
curl -sS -b "$JAR" -c "$JAR" -H "Accept: application/json" "$API/api/v1/auth/me" -w '\n%{http_code}\n' | tail -2
