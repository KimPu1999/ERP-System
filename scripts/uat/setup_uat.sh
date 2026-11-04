#!/usr/bin/env bash
#
# Phase 15 UAT — SETUP (isolated environment only).
#
# Builds the isolated `erp_uat` PostgreSQL database, applies the current
# migration set, and bootstraps exactly one synthetic platform-admin account
# whose password is taken from the environment (NEVER hardcoded).
#
# What this does NOT do:
#   - It does not seed fake business records. Business/ERP data is created BY
#     the testers through the real onboarding and CRUD workflows, which are
#     themselves UAT cases (UAT-BIZ-001…). This keeps fixtures honest and the
#     script small and reviewable.
#   - It does not modify any .env, queue, cron, or other environment.
#
# Required env:
#   UAT_ADMIN_PASSWORD   passphrase for the synthetic platform admin
#   UAT_PGUSER/UAT_PGPASSWORD  local PostgreSQL role allowed to create erp_uat (create role only; NOT superuser)
#
# Refuses (via uat_env.sh): any DB name without 'uat', any production-looking URI,
# any destructive op without explicit clearance.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/uat_env.sh"

DB="$UAT_DB_NAME"
uat_verify_name_allowed "$DB"

BACKEND="$SCRIPT_DIR/../../backend"

echo "── 1. Creating isolated UAT database '$DB' (if absent)…"
uat_psql_conn -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$DB'" | grep -q 1 || \
  uat_psql_conn -d postgres -c "CREATE DATABASE $DB OWNER ${UAT_PGUSER:-erp}"

echo "── 2. Disabling destructive helpers inside this session-only run…"
# Safety: migrations must be driver-safe; erp_uat is disposable.

echo "── 3. Applying migrations fresh on '$DB'…"
(
  cd "$BACKEND"
  APP_ENV=testing-local-uat \
  DB_CONNECTION=pgsql DB_DATABASE="$DB" DB_USERNAME="${UAT_PGUSER:-erp}" DB_HOST="${UAT_PGHOST:-127.0.0.1}" \
  php artisan migrate:fresh --force --no-interaction
)

echo "── 4. Bootstrapping synthetic platform admin (password from UAT_ADMIN_PASSWORD env)…"
if [[ -z "${UAT_ADMIN_PASSWORD:-}" ]]; then
  echo "FAIL: UAT_ADMIN_PASSWORD not set — refusing to create a hardcoded-password account." >&2
  exit 43
fi
(
  cd "$BACKEND"
  APP_ENV=testing-local-uat DB_CONNECTION=pgsql DB_DATABASE="$DB" DB_USERNAME="${UAT_PGUSER:-erp}" DB_HOST="${UAT_PGHOST:-127.0.0.1}" \
  php artisan tinker --execute='
    use App\Models\User;
    use Illuminate\Support\Facades\Hash;
    $u = User::firstOrCreate(
      ["email" => "uat-admin@uat.erp.local"],
      ["name" => "UAT Platform Admin", "password" => Hash::make(env("UAT_ADMIN_PASSWORD")),
       "email_verified_at" => now(), "status" => "active"]
    );
    echo "user:{$u->email}\n";
  '
  APP_ENV=testing-local-uat DB_CONNECTION=pgsql DB_DATABASE="$DB" DB_USERNAME="${UAT_PGUSER:-erp}" DB_HOST="${UAT_PGHOST:-127.0.0.1}" \
  php artisan platform:provision-admin uat-admin@uat.erp.local --yes
)

echo "── 5. UAT environment ready."
cat <<'EOF'
NEXT — tester checklist (all synthetic, all @uat.erp.local):
  1. Log in as uat-admin@uat.erp.local on the platform surface.
  2. Create tenant + business admin (UAT-PLAT-011, UAT-PLAT-016).
  3. Business admin completes onboarding via UI (UAT-BIZ-002).
  Deterministic fixture slugs and emails are listed in docs/phase15/UAT_MASTER_PLAN.md §5.
EOF
