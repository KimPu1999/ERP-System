# Production Environment Template — ERP Cloud API

Reviewed against: `backend/.env.example`, every `env()` read under `backend/config`, `app/Http/Middleware/ValidateProductionConfiguration.php`, the CORS config, the mail invitation flows and the migrations applied on 2026-11-03.

Review verdict on the previously proposed template: **incomplete — would fail two production gates at boot.**

## Gaps found

| # | Gap | Impact | Severity |
|---|---|---|---|
| G-1 | `FRONTEND_URL` missing | Defaults to `http://localhost:5173` → CORS allow-list contains localhost → `ValidateProductionConfiguration` throws **503 on every request**; all invitation/verification emails link to localhost | **Blocker** |
| G-2 | `APP_KEY` missing | Production validator throws **503**; cookies/sessions uninitialized | **Blocker** |
| G-3 | `QUEUE_CONNECTION=database` + `CACHE_STORE=database` without backed tables | Fresh schema had no `jobs`/`job_batches`/`cache`/`cache_locks` → runtime failure for 15 queueable job classes (report exports, supplier automation, ERP notifications). **Fixed** by migration `2026_11_03_000014_create_database_queue_and_cache_tables.php` (additive, idempotent). | **Blocker → Resolved** |
| G-4 | `DB_SSLMODE` not set | Falls back to `prefer` — acceptable, but `require` (or `verify-full` with the managed CA) is the production standard for a private DB host | Should fix |
| G-5 | Mailer "<configured-provider>" without the SMTP/TLS option block | `MAIL_SCHEME` (smtp/smtps), `MAIL_HOST/PORT/USERNAME/PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_TIMEOUT`, `MAIL_EHLO_DOMAIN` are read by `config/mail.php`; ACTUAL production validator rejects `MAIL_MAILER=log` | Blocker if left unset |
| G-6 | `TRUSTED_PROXIES` not configured | Behind a TLS-terminating proxy Laravel sees `http` + proxy host → signed email-verification URLs fail signature validation and client IPs are wrong for audit/security events | Should fix — now supported via `bootstrap/app.php` |
| G-7 | Billing + backup observability vars missing | `BILLING_PROVIDER`/`BILLING_WEBHOOK_SECRET`, `BACKUP_PROVIDER`/`BACKUP_RPO`/`BACKUP_RTO` are consumed by services/monitoring; defaults silently degrade | Should fix |
| G-8 | Hardening flags implicit | `SESSION_ENCRYPT`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`, `PLATFORM_MFA_REQUIRED`, `SANCTUM_TOKEN_EXPIRATION`, `SECURITY_MAX_EXPORT_ROWS` — set explicitly so they cannot drift with the default | Recommended |

Note on `SESSION_DOMAIN`/`SANCTUM_STATEFUL_DOMAINS`: only valid when the SPA and API share a root domain (SPA `app.example.com`, API `api.example.com`); otherwise omit `SESSION_DOMAIN` and list only the SPA origin(s), port included when non-443. Cookie/domain mismatch breaks the Sanctum SPA auth contract verified working in the local smoke test.

## Corrected template (placeholders only — generate/secret values belong in the secret manager)

```dotenv
APP_NAME="Enterprise ERP Cloud API"
APP_ENV=production
APP_KEY=<generated-via-php-artisan-key:generate --show>
APP_DEBUG=false
APP_URL=https://api.example.com
APP_LOCALE=en

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=<private-database-host>
DB_PORT=5432
DB_DATABASE=<production-database>
DB_USERNAME=<restricted-app-user>
DB_PASSWORD=<secret-manager-reference>
DB_SSLMODE=verify-full      # or require when the host cannot present a CA chain to validate

FRONTEND_URL=https://app.example.com

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=.example.com   # only for same-root-domain SPA deployments
SANCTUM_STATEFUL_DOMAINS=app.example.com,api.example.com

TRUSTED_PROXIES=<LB-in-proxy-CIDRs-or-*>   # required when nginx/ALB terminates TLS

QUEUE_CONNECTION=database     # tables added by 2026_11_03_000014 migration
CACHE_STORE=database          # tables added by 2026_11_03_000014 migration
CACHE_PREFIX=erp_prod_cache

PLATFORM_MFA_REQUIRED=true
SECURITY_MAX_EXPORT_ROWS=10000

MAIL_MAILER=smtp              # smtp (STARTTLS) or smtps (implicit TLS)
MAIL_SCHEME=smtps
MAIL_HOST=<mail-host>
MAIL_PORT=<mail-port>
MAIL_USERNAME=<mail-username>
MAIL_PASSWORD=<secret-manager-reference>
MAIL_EHLO_DOMAIN=api.example.com
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Enterprise ERP Cloud"

BILLING_PROVIDER=<configured-provider>
BILLING_TRIAL_DAYS=14
BILLING_GRACE_DAYS=7
BILLING_WEBHOOK_SECRET=<secret-manager-reference>

BACKUP_PROVIDER=<configured-provider>
BACKUP_RPO=<minutes>
BACKUP_RTO=<minutes>
```

## Driver-selection note (your template's `database` choices)

Now valid after the migration fix. Trade-off to record in `DEPLOYMENT_CHECKLIST.md`: sessions, cache and queue payload churn all land on the primary production database. Acceptable for initial production scale; when load warrants it, provision Redis (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_*`) — the config already supports those env vars and `.env.example` shows the shape. Do not mix: keep drivers consistent across the fleet of worker and web nodes.

## Verification performed

- `php artisan migrate --path=...2026_11_03_000014...` → `DONE` on the local `erp` database; tables confirmed via `psql`.
- `Cache::store("database")` put/get → `ok`; `Cache::lock()` → `locked`; `Queue::connection("database")->size()` → `0`. All live-executed.
- `php -l` clean for the migration and `bootstrap/app.php` changes.
