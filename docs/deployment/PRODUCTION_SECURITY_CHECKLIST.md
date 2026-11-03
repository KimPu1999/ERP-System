# Production Security Checklist — Enterprise ERP Cloud

Each item marks state: **[code]** = enforced/implemented in the repository today, **[config]** = shipped configuration file/step, **[ops]** = operator action at provisioning, **[verify]** = must be tested at staging gate.

## H — Host & network

| Control | State | Where |
|---|---|---|
| HTTPS enforcement, HTTP→HTTPS redirect | [config] | `infrastructure/nginx/api.conf` (`return 301`), smoke gate #6 |
| TLS 1.2+/1.3, HSTS | [config] | `api.conf`; verification in `DOMAIN_HTTPS_SETUP.md` |
| Firewall default-deny; only 80/443 public | [ops] | `HOSTING_REQUIREMENTS.md` §1 |
| SSH hardened (keys only, no root, restricted deploy account) | [ops] | `BACKEND_DEPLOYMENT.md` §1 |
| PostgreSQL private (no 5432 public) + TLS | [ops][code] | `.env.production.example` DB_SSLMODE, `POSTGRESQL_PRODUCTION.md` |
| Redis private + password | [ops][config] | `.env.production.example` REDIS_PASSWORD |
| Log rotation + time sync | [ops] | `HOSTING_REQUIREMENTS.md` §1 |

## A — Application

| Control | State | Where |
|---|---|---|
| APP_ENV=production, APP_DEBUG=false | [code] runtime middleware 503 gate | `ValidateProductionConfiguration` |
| APP_KEY provisioned, never rotated in place | [ops] | `ENVIRONMENT_VARIABLES.md` §10 |
| Secure encrypted cookies (Secure/HttpOnly/lax/encrypted) | [code][config] | session config + middleware check |
| Sanctum SPA CSRF protection intact (419 observed in live smoke) | [code][verify] | Phase 10 table; staging re-check step 7 |
| Credentialed CORS limited to explicit origin only (no wildcard) | [code][config] | `config/cors.php` — single `FRONTEND_URL` + `supports_credentials=true` |
| Trusted proxies set to explicit IPs | [config] | `bootstrap/app.php` `TRUSTED_PROXIES` |
| Security headers (nosniff, DENY frames, referrer, permissions policy) | [code] + [config] | `SecurityHeaders` middleware + both nginx/vercel header sets |
| Rate limiting on auth surfaces | [code][verify] | existing rate limiter (validated by tests; re-verify staging pre-launch) |
| MFA capability + requirement toggle | [code][config] | `PLATFORM_MFA_REQUIRED=true`, MFA flows + step-up middleware (`EnsureRecentStepUp`, 428 observed in tests) |
| Suspended account blocking at auth + workspace level | [code][verify] | `AuthenticationAccessTest` suite green |
| Tenant isolation (RLS policies + `ResolveTenant` middleware, invalid tenant fails closed) | [code][verify] | `TenantIsolationTest` etc.; staging spot-check |
| Audit logging of platform actions + supplier actions; immutable audit | [code] | platform_audit_logs migrations + `platform:security-prune` archive-hash path |
| File upload authorization + private-disk gating | [code][config] | `EMAIL_STORAGE_SETUP.md` §2 |
| .env / backups / logs never web-served | [config] | nginx deny rules for dotfiles, `/storage`, `/vendor`; vercel headers; **never commit .env** |
| No debug output on errors in production | [code][config] | APP_DEBUG false + smoke gate #5 |
| Admin URLs require backend authorization (platform middleware) | [code] | `platform.admin` alias middleware (RequirePlatformSuperAdmin) |

## S — Supply chain

| Control | State | Where |
|---|---|---|
| `composer audit` (all) gates in CI | [code] | `.github/workflows/ci.yml` |
| `npm audit --audit-level=high` gate | [code] | ci.yml web job |
| Dependabot/scanning cadence | [ops] | operator enables repository security features at provisioning |
| Lockfile-only installs in all pipelines | [code] | `npm ci`/`composer install` usage |

## M — Monitoring & alerting

| Item | State | Where |
|---|---|---|
| API 5xx/latency/availability | [code] MonitoringService collector + [ops] external uptime monitor | Step 16 |
| PostgreSQL availability, connection saturation | [code] health check flag + [ops] DB metrics | `POSTGRESQL_PRODUCTION.md` §2 |
| Queue backlog + failed jobs | [verify] alert on `failed_jobs` > 0 for financial queues | `QUEUE_SCHEDULER_SETUP.md` |
| Scheduler freshness (>5 min stale ⇒ alert) | [verify] | `QUEUE_SCHEDULER_SETUP.md` §2 |
| E-mail send failures | [verify] provider webhooks/logs | `EMAIL_STORAGE_SETUP.md` |
| Disk usage, TLS expiry, backup failures | [verify-ops] | runbook + domain docs |
| Alert routing to authorized operators | [ops] | see with `docs/incident-response.md` |
| Healthy/Degraded/Down/Unknown states honest | [code] | MonitoringService semantics (no fake uptime metrics — repo-wide rule) |

## Forbidden (never)

- Exposing `.env`, DB/app credentials, APP_KEY, mail keys, private documents, backups, or sensitive Laravel logs via web, logs, or CI output.
- Weakening CSRF, disabling tenant middleware, or bypassing RLS "temporarily".
- Wildcard CORS origins with credentials; wildcard `SANCTUM_STATEFUL_DOMAINS` covering unknown subdomains.
- `migrate:fresh`/`reset`/`db:wipe` on staging/production.
