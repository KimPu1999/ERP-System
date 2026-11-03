# Environment Variables — Enterprise ERP Cloud

Authoritative inventory of every environment variable the application reads, verified against the repository (2026-11-03). Sources: `backend/.env.example`, `backend/.env.production.example`, `apps/web/.env.example`, `apps/web/.env.production.example`, `config/*.php`, `bootstrap/app.php`, `phpunit.xml.dist`, `.github/workflows/ci.yml`.

## 1. Backend core

| Variable | Production required value | Where consumed | Notes |
|---|---|---|---|
| `APP_ENV` | `production` | `bootstrap/app.php` via `ValidateProductionConfiguration`, `erp:release-check` | Anything else blocks release check |
| `APP_DEBUG` | `false` | Debug output gating | Non-negotiable in production |
| `APP_URL` | HTTPS api origin | URL generation | Never HTTP |
| `APP_KEY` | `<secret>` (base64) | Encryption (cookies, MFA secrets, `encrypted_*` columns) | **Never regenerate after provisioning** — see §Key rotation |
| `APP_LOCALE` | `en` | `SetLocale` middleware (`en`/`mm` supported) | |

## 2. Database

| Variable | Notes |
|---|---|
| `DB_CONNECTION` | `pgsql` (fixed by tests/CI) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE` | Private network host; separate DBs per environment (`erp_prod` / staging counterpart); never the developer Docker instance |
| `DB_USERNAME`, `DB_PASSWORD` | Least-privilege runtime role for the app; migration DDL role is injected only during approved migrations (see `POSTGRESQL_PRODUCTION.md`) |
| `DB_SSLMODE` | `verify-full` (or `verify-ca`) on managed DB — enforced by `erp:release-check` |
| `DB_POOL_MAX`, `DB_STATEMENT_TIMEOUT_MS` | Server-side timeouts configured on the DB; app-side caps documented in `POSTGRESQL_PRODUCTION.md` |

## 3. Cache / Queue / Sessions / Sanctum

| Variable | Notes |
|---|---|
| `CACHE_STORE` | `redis` in staging/production (`array` is test-only) |
| `QUEUE_CONNECTION` | `redis` in staging/production (`sync` local/tests only) |
| `REDIS_HOST/PORT/PASSWORD/CLIENT` | private Redis, password mandatory |
| `SESSION_DRIVER` | `database` (tests use `array`) |
| `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` | `true/true/true/lax` (release-check enforces secure) |
| `SESSION_DOMAIN` | Shared root domain (e.g. `example.com`) — frontends on `app.` and API on `api.` subdomains |
| `SESSION_LIFETIME` | Minutes; chosen with `SANCTUM_TOKEN_EXPIRATION` |
| `SANCTUM_STATEFUL_DOMAINS` | Comma list of stateful SPA domains incl. api origin |
| `SANCTUM_TOKEN_EXPIRATION` | Seconds (default 43200), matches access-token expiry for non-SPA usage |

## 4. Networking & CORS

| Variable | Notes |
|---|---|
| `TRUSTED_PROXIES` | Exact nginx/LB IP(s); consumed in `bootstrap/app.php` (`trustProxies`); never `0.0.0.0/0` |
| `FRONTEND_URL` | The exact frontend origin (used by `config/cors.php` as the single credentialed allowed origin); must not be localhost in production (middleware validation) |

## 5. Mail

| Variable | Notes |
|---|---|
| `MAIL_MAILER` | Real provider only; `log` is rejected in production by middleware |
| `MAIL_SCHEME` | `smtp` (STARTTLS) or `smtps` (implicit TLS) per provider capability (see `config/mail.php`) |
| `MAIL_HOST/PORT/USERNAME/PASSWORD` | From secret manager |
| `MAIL_EHLO_DOMAIN` | Verified sending domain |
| `MAIL_FROM_ADDRESS/NAME`, `MAIL_TIMEOUT` | Sender identity must be a verified sender |

## 6. Platform / security / billing / storage / backup

| Variable | Notes |
|---|---|
| `PLATFORM_MFA_REQUIRED` | `true` in production (default); test suite forces `false` (documented in `phpunit.xml.dist`) |
| `SECURITY_MAX_EXPORT_ROWS` | Export risk cap (default 10000) |
| `BILLING_PROVIDER`, `BILLING_TRIAL_DAYS`, `BILLING_GRACE_DAYS`, `BILLING_WEBHOOK_SECRET` | Billing/manual provider selection; webhook secret in secret manager |
| `DISK_DRIVER_PRIVATE` / disk configuration | Object storage for private assets — see `EMAIL_STORAGE_SETUP.md` |
| `BACKUP_PROVIDER`, `BACKUP_RPO`, `BACKUP_RTO` | Targets recorded config-side (15 min / 120 min); see `BACKUP_RECOVERY_RUNBOOK.md` |

## 7. Frontend (Vite — browser-visible)

| Variable | Scope rule |
|---|---|
| `VITE_API_URL`, `VITE_API_ORIGIN` | Public API origin; baked into assets. Must never contain secrets, DB credentials, or private tokens — **Vite prepends `VITE_` values into the shipped JS** |
| `VITE_TENANT_ID` | Optional local-only context; never a real tenant identifier in production |

## 8. CI-only (`.github/workflows/ci.yml`)

`APP_ENV=testing`, `APP_DEBUG=true`, fixed `APP_KEY` test value, `DB_*` pointing to the `postgres` job service, `PLATFORM_MFA_REQUIRED=false` (via `phpunit.xml.dist`). These are test-composition values and are never valid for staging/production.

## 9. Secret handling rules

1. Never commit a filled `.env`; the repo contains only `*.example` templates.
2. Production `.env` lives once on the server: `/var/www/erp-api/shared/.env` (0600, owned by the deployment account) and is sourced from a secret manager.
3. Local/dev secrets (`erp-local-only`, `base64:AAAA…`) are placeholders and must never appear in any server environment (modeled by `ValidateProductionConfiguration` check on the CORS origin).
4. Never print secrets in CI/workflow logs; the `deploy.yml` gate references secrets without echoing them.

## 10. APP_KEY rotation (do NOT perform during deployment prep)

`APP_KEY` encrypts cookies, MFA recovery material, and `encrypted_*` database columns (`users.mfa_secret_encrypted`, supplier bank-account fields — see migrations). Rotating it makes all existing encrypted values undecryptable. Rotation therefore requires a dedicated two-phase procedure (dual-key decryption window with an `APP_PREVIOUS_KEYS` shim) that is **not yet implemented** in this codebase. Until that procedure exists and is reviewed, the rule is: provision one key per environment at setup and never rotate it in place.
