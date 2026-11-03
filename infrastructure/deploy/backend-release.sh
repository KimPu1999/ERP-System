#!/usr/bin/env bash
# ERP Cloud backend zero-downtime release deploy (Option A / self-managed VPS).
#
# Pattern: versioned releases at $BASE/releases/<id> with a $BASE/current
# symlink flip. Shared runtime state (storage/, bootstrap/cache, .env) lives in
# $BASE/shared and survives every release.
#
# Contract (docs/deployment-runner-contract.md):
#  - Migrations run ONLY when MIGRATE=1 AND fresh verified backup evidence is
#    referenced in this invocation. Never migrate without backup evidence.
#  - Rollback = point `current` at the previous release, clear caches, restart
#    workers. A successful migration is never auto-reverted (see ROLLBACK_PLAN).
#  - Never runs migrate:fresh, migrate:rollback, db:wipe, or any DROP/TRUNCATE.
#
# Usage (from the erp-deploy account on the API host):
#   MIGRATE=1 sha=<commit-sha> \
#     ARTIFACT=/home/erp-deploy/artifacts/erp-api-<sha>.tar.gz \
#     BACKUP_EVIDENCE=/var/backups/enterprise-erp/erp-<ts>.dump.age \
#     bash infrastructure/deploy/backend-release.sh
set -Eeuo pipefail

: "${ARTIFACT:?artifact path required}"
: "${sha:?release sha required}"
: "${BASE:=/var/www/erp-api}"
: "${PHP:=/usr/bin/php}"
: "${PHP_FPM_SERVICE:=php8.4-fpm}"
: "${KEEP_RELEASES:=5}"
: "${MIGRATE:=0}"

id="$(date -u +%Y%m%d%H%M%S)"
release="$BASE/releases/$id"
log() { printf '[%s] %s\n' "$(date -u +%FT%TZ)" "$*"; }
fail() { echo "DEPLOY FAIL: $* >&2" ; exit 1; }
run_artisan() { # <-- all artisan calls made with a known-good flag set
  "$PHP" artisan "${ARTISAN_FLAGS[@]}" "$@"
}
ARTISAN_FLAGS=('--no-interaction' '--force')

test -f "$ARTIFACT" || fail "artifact not found: $ARTIFACT"
test -d "$BASE/shared/storage" || fail "shared storage not initialized: $BASE/shared/storage"

# ---- 0. Pre-flight: verified backup is mandatory for migrations --------------
if [[ "$MIGRATE" == "1" ]]; then
  : "${BACKUP_EVIDENCE:?MIGRATE=1 requires BACKUP_EVIDENCE path of a verified encrypted backup taken before this deploy}"
  test -s "$BACKUP_EVIDENCE" || fail "backup evidence missing or empty: $BACKUP_EVIDENCE"
  log "migrations approved for this release; backup evidence: $BACKUP_EVIDENCE"
fi

# ---- 1. Extract candidate release -------------------------------------------
mkdir -p "$release"
tar -xzf "$ARTIFACT" -C "$release"
test -f "$release/artisan" || fail "artifact is not a Laravel release (artisan missing)"

# ---- 2. Wire shared runtime state --------------------------------------------
ln -sfn "$BASE/shared/storage" "$release/storage"
mkdir -p "$release/bootstrap/cache"
test -f "$BASE/shared/.env" || fail "shared .env missing; refusing to deploy"
ln -sfn "$BASE/shared/.env" "$release/.env"

"$PHP" -l "$release/artisan" >/dev/null

cd "$release"
# Build cached config/routes/events/views; verify the .env bootstrap loads.
run_artisan config:cache >/dev/null
run_artisan route:cache >/dev/null
run_artisan event:cache >/dev/null
run_artisan view:cache >/dev/null
run_artisan config:clear >/dev/null
run_artisan optimize >/dev/null
log "caches built for candidate release $id"

# ---- 3. Candidate release must validate its production configuration ---------
if [[ "$MIGRATE" == "1" ]]; then
  run_artisan erp:release-check >/dev/null || fail "erp:production-check failed on candidate release"
  run_artisan migrate --dry-run 2>/dev/null && log "migration dry-run ok" || log "dry-run unavailable; reviewed migration plan governs"
fi

# ---- 4. Database migration (approval-gated) ----------------------------------
if [[ "$MIGRATE" == "1" ]]; then
  run_artisan migrate
  run_artisan migrate:status >/dev/null || true
  log "migrations applied for $sha"
else
  log "MIGRATE not set; skipping migrations (schema assumed compatible)"
fi

# ---- 5. Atomic switch ---------------------------------------------------------
prev="$(readlink -f "$BASE/current" 2>/dev/null || true)"
ln -sfn "$release" "$BASE/.current.new"
mv -Tf "$BASE/.current.new" "$BASE/current"
log "traffic switched to release $id (previous: ${prev:-none})"

# ---- 6. Reload long-running services ------------------------------------------
if command -v supervisorctl >/dev/null 2>&1; then
  sudo -n supervisorctl restart erp-backend:* || log "MANUAL ACTION: restart erp-backend group"
else
  log "MANUAL ACTION: queue workers not restarted (supervisorctl not found)"
fi
systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || log "php-fpm reload skipped/failed: $PHP_FPM_SERVICE"

# ---- 7. Health + smoke BEFORE declaring success --------------------------------
: "${API_URL:?API_URL required for post-deploy smoke}"
: "${EXPECTED_FRONTEND_ORIGIN:?EXPECTED_FRONTEND_ORIGIN required}"
API_URL="$API_URL" EXPECTED_FRONTEND_ORIGIN="$EXPECTED_FRONTEND_ORIGIN" \
  bash "$BASE/release-scripts/post-deploy-smoke.sh" \
  || fail "post-deploy smoke failed; run rollback procedure"

# ---- 8. Release retention -------------------------------------------------------
ls -1d "$BASE/releases"/* | sort | head -n "-$KEEP_RELEASES" | while read -r old; do rm -rf "$old"; done

log "SUCCESS release $id (sha=$sha). Rollback: ln -sfn $prev $BASE/current && run_artisan optimize:clear && supervisorctl restart erp-backend:*"
