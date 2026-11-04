#!/usr/bin/env bash
#
# Phase 15 UAT — TEARDOWN (isolated environment ONLY).
#
# Drops the isolated `erp_uat` database. It refuses to touch:
#   - `erp` (development business data — Phase 15 evidence shows real tenants exist)
#   - `erp_test` (automation suite database)
#   - `postgres`/templates/production-like names
#   - any database name not containing 'uat'
# Double clearance required: UAT_CONFIRM_REMOVE="REMOVE UAT DATA".
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/uat_env.sh"

DB="$UAT_DB_NAME"
uat_verify_destructive_clearance "$DB"

echo "You are about to DROP the ISOLATED UAT environment '$DB'."
echo "Existing connections will be terminated first (UAT-only sessions)."
uat_psql_conn -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$DB' AND pid <> pg_backend_pid();" || true
uat_psql_conn -d postgres -c "DROP DATABASE IF EXISTS $DB;"
echo "UAT environment '$DB' removed. No other database was touched."
