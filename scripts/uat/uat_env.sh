#!/usr/bin/env bash
#
# UAT safety guard — shared by setup_uat.sh / teardown_uat.sh.
#
# HARD RULES:
#   1. Every database name except the isolated UAT one is REFUSED.
#      - Refused outright at any time: erp (dev), erp_test (automation), anything not containing "uat".
#   2. Destructive actions additionally require the operator to set
#      UAT_CONFIRM_REMOVE="REMOVE UAT DATA" in the environment.
#   3. These scripts never touch Laravel .env, never change app config, and never
#      connect to any system not named in the UAT workflow (i.e. no production
#      connection strings are accepted — a production-looking PGURI is refused).
#
set -euo pipefail

UAT_DB_NAME="erp_uat"
FORBIDDEN_EXACT_NAMES=("erp" "erp_test" "postgres" "template0" "template1" "production")

uat_fail() {
  echo "UAT-GUARD REFUSAL: $*" >&2
  exit 42
}

# Refuse any connection URI that does not look like a local/staging instance.
uat_refuse_production_uri() {
  local uri="$1" || true
  [[ "$uri" =~ (localhost|127\.0\.0\.1|\.local|uat) ]] || \
    uat_fail "Refusing non-local PostgreSQL URI — UAT only runs against a local/staging instance you control."
}

uat_verify_name_allowed() {
  local db="$1"
  local name forbidden
  for forbidden in "${FORBIDDEN_EXACT_NAMES[@]}"; do
    [[ "$db" == "$forbidden" ]] && uat_fail "Database '$db' is a protected environment ($db must never be dropped)."
  done
  [[ "$db" == *uat* ]] || uat_fail "Database name '$db' is not a UAT environment (must contain 'uat')."
  echo "$db"
}

# Destructive double-lock.
uat_verify_destructive_clearance() {
  local expected_db="$1"
  [[ "${UAT_CONFIRM_REMOVE:-}" == "REMOVE UAT DATA" ]] || \
    uat_fail "Set UAT_CONFIRM_REMOVE='REMOVE UAT DATA' to authorize removal of $expected_db."
  uat_verify_name_allowed "$expected_db"
}

uat_psql_conn() {
  # Uses the local credentials the operator exports: UAT_PGUSER / UAT_PGPASSWORD / UAT_PGHOST.
  # Defaults are the local dev-instance role; NEVER accept a superuser/production role silently.
  UAT_PGUSER="${UAT_PGUSER:-erp}"
  UAT_PGHOST="${UAT_PGHOST:-127.0.0.1}"
  if [[ -n "${UAT_PGPASSWORD:-}" ]]; then export PGPASSWORD="$UAT_PGPASSWORD"; fi
  psql -h "$UAT_PGHOST" -U "$UAT_PGUSER" "$@" 2>&1
}
