# Deployment Architecture — Enterprise ERP Cloud

Phase 11. Status: **planned/pending approval** — no infrastructure has been provisioned and no live deployment has occurred. This document records verified options and constraints; nothing is selectable until the approval gate in `GO_LIVE_CHECKLIST.md` clears.

## 1. Verified application truths (audit result, 2026-11-03)

| Fact | Evidence |
|---|---|
| Backend: Laravel 13 + Sanctum 4, PHP `^8.3` (CI runs PHP 8.4), Bcmath | `backend/composer.json` |
| Frontend: React 19 + TS 5.7 + Vite 6, Vite reads `VITE_API_URL`/`VITE_API_ORIGIN` | `apps/web/src/lib/api.ts`, `apps/web/.env.example` |
| Database: PostgreSQL, RLS enforced by migration | `database/migrations/.../enable_tenant_row_level_security.php`, `DATABASE_FINDINGS` docs |
| Queue: `QUEUE_CONNECTION=redis` in templates; `QUEUE_CONNECTION=sync` local dev; testing forces `sync` | `.env*`, `phpunit.xml.dist` |
| Sessions: Sanctum SPA cookie sessions (`SESSION_DRIVER=database`, CSRF handshake at `/sanctum/csrf-cookie`) | `config/sanctum.php`, Phase 10 live smoke table |
| Production config gate: `ValidateProductionConfiguration` middleware (503 when APP_DEBUG on, APP_KEY missing, `MAIL_MAILER=log`, insecure cookies, localhost CORS) | `app/Http/Middleware/ValidateProductionConfiguration.php` |
| Full test suite baseline | **176 passed (842 assertions)** on 2026-11-03 — see `STAGING_TEST_RESULTS.md` |
| Existing infra assets | `infrastructure/nginx/{web,api}.conf`, `infrastructure/supervisor/`, `infrastructure/cron/`, `infrastructure/backup/*`, `backend/Dockerfile`, `apps/web/vercel.json` |

## 2. Option A — React on Vercel + Laravel on a self-managed Linux VPS

```
Browser ──> app.example.com (Vercel SPA, immutable assets)
         ──> api.example.com (VPS: nginx → php-fpm → PostgreSQL 17, private Redis, age-encrypted backups)
```

| Dimension | Assessment (project-verified, no invented provider prices) |
|---|---|
| Costs | Monthly VPS rental + PostgreSQL hosting (or self-hosted on the VPS) + object storage + mail + Vercel free-tier suitability must be quoted **at signup with incumbent providers**; the repo contains no provider contract, so no figures are asserted here |
| Resource limits | VPS nodes fixed RAM/CPU; echarts-heavy frontend offloaded to Vercel CDN (Phase 10 already split the bundle: app 295.62 kB gzip) |
| DB backups | Self-managed: `infrastructure/backup/postgres-backup.sh` (pg_dump → age-encrypted → retention-cleanup) + `postgres-restore.sh` with drill procedure in `BACKUP_RECOVERY_RUNBOOK.md` |
| Maintenance | OS patching, PHP 8.4 update cadence, cert renewal, supervisor health, Redis persistence — all **operator-owned** |
| Scaling | Vertical only, without a second app host; horizontal scale requires shared NFS/object storage for `storage/` and a single-scheduler host (`withoutOverlapping` locks are Redis-cache backed, so cross-host locking works only with a shared cache) |
| Deployment complexity | Moderate: release scripts exist (`infrastructure/deploy/backend-release.sh`) but hosts must be provisioned manually once |
| Security responsibilities | Full server hardening (`PRODUCTION_SECURITY_CHECKLIST.md` §H), firewall, SSH — operator-owned |

## 3. Option B — React on Vercel + managed Laravel hosting + managed PostgreSQL/quota services

Not accredited as available or priced because no provider account exists in-repo. If pursued, the binding constraints from the codebase are:

1. PHP ≥ 8.3 with `bcmath`, `pcntl` (queue workers, jobs use them), `pdo_pgsql`, `zip`.
2. Managed PostgreSQL **must** support RLS (`row-level security` policies) and non-superuser role separation — confirm before signup; some managed tiers disable `ALTER ROLE` used by least-privilege setup in `POSTGRESQL_PRODUCTION.md`.
3. Persistent writable `storage/` (private disk) — ephemeral containers drop uploads; `EMAIL_STORAGE_SETUP.md` requires object storage for invoices/documents.
4. Cron scheduler + long-running worker support with `stopwaitsecs` ≥ job timeout 120 s.
5. TLS to the managed DB (`DB_SSLMODE=verify-full`) — `erp:release-check` hard-fails otherwise.
6. Credentialed CORS + same-root-domain cookies (Sanctum): frontend and API must share `example.com`.

## 4. Selected architecture (decision gate)

Option A is the architecture this repository is already prepared for (nginx/Supervisor/cron/backup scripts). Option B is viable only if the six constraints above are confirmed with a provider. **Selection is deferred to the explicit user approval gate** — see `GO_LIVE_CHECKLIST.md` Item 1.

## 5. Environment separation

| Environment | App env | Data | Infra | Deployed by |
|---|---|---|---|---|
| Development | `local` | seeded local Docker PosgreSQL + local Redis (`infrastructure/docker-compose.yml`) | none live | `npm run dev`, `php artisan serve` |
| Staging | `production` config values, `APP_DEBUG=false`, live e-mail but a staging sender | migrate-foward-only disposable state; **no real business data, no developer PII** (approved plan required before any import) | separate VPS/DB instance, same topology as production | `deploy.yml` → runner (staging) |
| Production | `production` | real tenant data + immutable audit trail | separate VPS/DB + monitoring + backups | `deploy.yml` → runner (production, GitHub-protected environment approval) |

Staging and production must never share a database, Redis, storage bucket, or `.env`.
