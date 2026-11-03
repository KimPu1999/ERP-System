# Backend Deployment — Enterprise ERP Cloud (Laravel API)

Prepared deployable sequence with the repository's actual files. **No server exists yet; every below step is a runbook for the approval-gated execution.**

## 1. Server provisioning checklist (Option A, matches `HOSTING_REQUIREMENTS.md`)

1. Ubuntu 24.04 LTS (or Debian 12) host; unattended upgrades for security patches.
2. PHP 8.4 FPM + `bcmath pcntl pdo_pgsql zip phpredis`; CLI + FPM both via `php -v = 8.4`.
3. Nginx + PHP-FPM pool: separate unix socket `php8.4-fpm-erp.sock`; fpm pool user unprivileged (`erp-fpm`), deploy user (`erp-deploy`) separate from runtime user (least privilege).
4. Web root **must** be `/var/www/erp-api/current/public` (Laravel `public/`); never the repo root.
5. Permissions: `storage/` and `bootstrap/cache/` writable by the fpm user via the shared bind (`/var/www/erp-api/shared`), 0775 — **never set 0777 recursively**.
6. SSH: keys only, root login disabled, ports restricted (fail2ban recommended); deploy account limited to repo + service reload sudoers whitelist.
7. Firewall: default-deny inbound; allow 80/443; PostgreSQL/Redis only on loopback/private network.
8. Logrotate configs for app + supervisor + nginx; time synchronisation enabled.
9. Certificates via ACME automation (`acme.sh`/`certbot`) with auto-renewal; see `DOMAIN_HTTPS_SETUP.md`.

## 2. Runtime configuration bootstrap (once per host/planet)

```bash
sudo mkdir -p /var/www/erp-api/{releases,shared/storage/framework/{cache,sessions,testing,views}}
sudo install -o erp-deploy -g erp-deploy -m 0600 /dev/null /var/www/erp-api/shared/.env
# Fill /var/www/erp-api/shared/.env from the secret manager using
# backend/.env.production.example as the checklist, then verify with:
sudo -u erp-deploy php artisan erp:release-check   # in the release dir
```

`ValidateProductionConfiguration` middleware is the runtime backstop: if APP_DEBUG, missing APP_KEY, `MAIL_MAILER=log`, insecure cookies, or a localhost CORS origin are present, it returns 503 — observed behavior, do not weaken this middleware.

## 3. Release flow (`infrastructure/deploy/backend-release.sh`)

```
ARTIFACT=<tarball built from a verified commit>
sha=<commit-sha>
MIGRATE=0|1                             # 1 only after EMAIL approval + backup evidence
BACKUP_EVIDENCE=<verified .dump.age>    # mandatory when MIGRATE=1
API_URL=https://api.example.com
EXPECTED_FRONTEND_ORIGIN=https://app.example.com
bash infrastructure/deploy/backend-release.sh
```

Script guarantees (set `-Eeuo pipefail`, all step-gated):

1. Extracts candidate to `/var/www/erp-api/releases/<id>`; fails if not a Laravel artifact.
2. Links shared storage/.env; caches config/routes/events/views; runs `erp:release-check` on the candidate when migrating; migration `--dry-run` observed (informational not binding).
3. Runs migrations **only** when explicitly approved with fresh backup evidence; never `migrate:fresh`, never rollback.
4. Atomic symlink switch `current.tmp → current`.
5. Restarts supervised queue workers (supervisor group `erp-backend`) — workers MUST be restarted after each release so they pick up new code; unmanaged terminals are prohibited.
6. Reloads php-fpm.
7. Runs `infrastructure/deploy/post-deploy-smoke.sh` (6 unauthenticated checks: health, 401 contract, CSRF cookies, CORS preflight from the exact frontend origin only, no debug output, HTTPS redirect). Smoke failure aborts the runner and triggers `ROLLBACK_PLAN.md`.
8. Prunes old releases (keeps 5 for rollback).

## 4. Smoke checks included (`infrastructure/deploy/post-deploy-smoke.sh`)

| Gate | Assertion |
|---|---|
| Health | `GET /api/v1/health` == 200 (TLS-validated) |
| Auth contract | `GET /api/v1/me` unauthenticated == 401 |
| CSRF handshake | `GET /sanctum/csrf-cookie` issues `XSRF-TOKEN` + session cookies |
| CORS | preflight from `EXPECTED_FRONTEND_ORIGIN` only accepted |
| Debug leak | 404/5xx bodies never contain framework trace markers |
| HTTPS | plain HTTP must redirect (3xx) to HTTPS |

## 5. Migration policy during releases

- `php artisan migrate --force` (forward-only). NO `migrate:fresh`, `migrate:rollback`, `db:wipe` on staging/production (no exceptions; Phase 10 audit §7 precedent).
- Schema changes must be backward-compatible (expand/contract); the write path stays valid while the previous release serves traffic.
- Destructive or locking operations (dropping columns, `NOT NULL` backfills on hot tables) require a two-release plan and explicit written approval.
- Migration status is verified by `migrate:status` post-run; logs published into the release record.

## 6. Rollback summary (full detail: `ROLLBACK_PLAN.md`)

`previous release re-linked` + `optimize:clear` + worker restart; database migrations are **never** auto-reverted: that decision is a separate DB recovery procedure with a written incident record.

## 7. Verifications required on staging (record against `STAGING_TEST_RESULTS.md`)

- Platform and business login; suspended account rejection; expired-session or 419 flows.
- Tenant isolation spot-checks for two co-resident tenants (automation exists in backend suite; sample verified in staging deployment's Postgres logs).
- Queue processing of a safe, non-financial test job (purchasing automation has a safe allowlist) and scheduler execution log freshness.
- `/api/v1/health` DB+cache+queue true flags observed in staging monitoring.
