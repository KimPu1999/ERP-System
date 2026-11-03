# PROJECT_AUDIT.md — Phase 10 Platform Audit

Date: 2026-11-03
Scope: Full audit of Enterprise ERP Cloud (Phases 1–9 codebase) against actual code, live PostgreSQL schema, executed tests and live API smoke tests.
Rules followed: evidence-only findings; no destructive operations; no data resets; no fabricated results.

## 1. Repository Inventory

| Area | Location | Verified State |
|---|---|---|
| Backend | `backend/` — Laravel 13.39.0, PHP requirement `^8.3`, Sanctum 4, Pest | Local PHP runtime 8.5.6 |
| Frontend | `apps/web/` — React 19 + TypeScript 5.7 + Vite 6, Tailwind 3, react-query 5, echarts 6, react-hook-form, Playwright, Vitest | Typecheck/lint/build/tests all pass |
| Database | PostgreSQL, `DB_CONNECTION=pgsql`, 89+ migrations (through `2026_11_03_000013`) | All migrations applied (`migrate:status` = all `Ran`) |
| CI | `.github/workflows/ci.yml` (API: PHP 8.4.1 + postgres:17 service + migrations + Pest + lint + `composer audit`; Web: `npm ci` + `npm audit` + typecheck + lint + build + tests), `deploy.yml` (gated by GitHub environment approvals + release docs check) | Present |
| Infrastructure | `infrastructure/nginx/web.conf`, `infrastructure/backup/postgres-backup.sh` (pg_dump + AES via `age`, 30-day retention), `postgres-restore.sh` | Present, fail-fast env-var contract |
| Docs | `docs/` — release-checklist, backup-restore, incident-response, security-review, phase 5–11 design docs | Present |

Entry points: Backend — `backend/bootstrap/app.php` (withRouting: `routes/web.php` + `routes/api.php` + health at `/up`), API prefix `v1`. Frontend — `apps/web/src/main.tsx` (`src/App.tsx` router).

Middleware stack: `SecurityHeaders`, `SetLocale`, `ValidateProductionConfiguration`, `statefulApi()` (Sanctum SPA cookies), guest redirect suppressed for `api/*`, aliases for `active.user`, `recent.stepup`, `tenant` (ResolveTenant), `supplier.portal`, `platform.admin`.

## 2. Module Classification

| # | Module | Classification | Evidence |
|---|---|---|---|
| 1 | Authentication & Email Verification | **Working and tested** | `AuthenticationAccessTest`, MFA controllers/tests pass; live smoke: `401` unauth, CSRF→`422` validation contract reproduced |
| 2 | Roles, Permissions & Tenant Security | **Working and tested** | `TenantIsolationTest` (invalid tenant fails closed; platform super admin cannot bypass membership), RLS migrations applied |
| 3 | Platform Overview | **Working and tested** | `PlatformBusinessManagementTest`, e2e `platform dashboard renders live authorized data` |
| 4 | Business Management | **Working and tested** | Supplier/procurement/inventory/sales/accounting suites all pass (176 tests green) |
| 5 | Business Admin Management | **Working and tested** | `StaffMembershipPhaseTest`, dedicated index migration applied |
| 6 | Subscription Plans | **Working and tested** | `PlatformBillingManagementTest`, `PlatformBillingPhaseTest` |
| 7 | Billing, Payments & Invoices | **Working and tested** | Idempotency migration (`add_platform_invoice_idempotency`), billing tests pass |
| 8 | Platform Analytics & Reports | **Working and tested** | `PlatformAnalyticsPhaseTest`, analytics index migrations |
| 9 | System Monitoring | **Working and tested** | `PlatformMonitoringPhaseTest`, monitoring tables migration |
| 10 | Security Center & Audit Logs | **Working and tested** | `PlatformSecurityPhaseTest`, audit immutability migration, e2e security center |
| 11 | Platform Settings & Branding | **Working and tested** | `PlatformSettingsPhaseTest`, settings tables + policy-read migrations |
| 12 | Localization (EN/MM) | **Working, partially verified** | `i18n.test.ts` enforces EN fallback; `check-i18n` script present; full UI-catalogue review outstanding |
| 13 | Backup & Operational Readiness | **Implemented but unverified (restore drill not run)** | Scripts exist and fail-fast; no executed restore verification in repo evidence |

## 3. Previously-Reported Defects — Reproduction Results

| Reported issue | Reproduction result |
|---|---|
| Missing `users.platform_role` column | **Not a live defect.** Information-schema query over live DB: column absent *by design*; role is expressed via `users.is_platform_super_admin` + role assignments. `grep platform_role` over all PHP: 0 references. No code or route expects it. |
| Duplicate supplier migration index | **Not a live defect.** `2026_11_02_000001_reconcile_supplier_invoice_indexes` already reconciled; live `pg_indexes` on `supplier_invoice_submissions` shows exactly 4 unique-purpose indexes, no duplicates. |
| Invalid tenant/company references | Not reproduced. Tenant-isolation feature tests (incl. cross-tenant read/write and platform-super-admin bypass) pass against the real schema. |
| Dev API server unresponsive to first POST (observed during audit) | **Environmental, not a code defect.** A long-running `php artisan serve` (:8000, started early morning) stopped serving; a fresh server reproduced the full SPA auth contract correctly. Monitoring of the single-process dev server recommended; no code change justified — `php -S` is single-worker and production uses nginx+php-fpm (`infrastructure/nginx/web.conf`). |

## 4. Live API Smoke Tests (executed)

| Check | Result |
|---|---|
| `GET /api/v1/health` | `200` `{status: ok, checks: {database: true, cache: true}}` |
| `GET /api/v1/me` unauthenticated | `401` |
| `GET /api/v1/platform/tenants` unauthenticated | `401` |
| CSRF handshake `GET /sanctum/csrf-cookie` | Sets `XSRF-TOKEN` + session cookies (`domain=localhost`, `samesite=lax`, session cookie `httponly`) |
| `POST /api/v1/auth/login` without CSRF | `419` (correct: stateful Spa contract; for browser fetch the client fetches the CSRF cookie first — implemented in `apps/web/src/lib/api.ts`) |
| `POST /api/v1/auth/login` with CSRF, invalid payload | `422` with field errors `email`, `password` |

## 5. Prioritized Defect Inventory

### F-001 — Single-bundle frontend payload (Performance, Medium-low)
- Reproduction: `npm run build` → one `index-*.js` 2,767.55 kB (746 kB gzip) + warning.
- Root cause: `vite.config.ts` had no `manualChunks`; echarts (~1.1 MB) bundled into app chunk.
- Fix applied this phase: vendor split (`react-vendor`, `echarts`, `ui-vendor`) in `apps/web/vite.config.ts`.
- Verification: rebuild → app chunk 1,415.59 kB (295.62 kB gzip), echarts isolated 1,145.86 kB (385.68 kB gzip, cacheable independently); typecheck + unit tests rerun, all pass.
- Residual: larger kbyte counts before gzip; further route-level lazy loading is a **Deferred Enhancement** (needs route refactor).

### F-002 — E2E coverage limited to Platform UI shell (QA, Medium for release claims)
- Actual state: `tests/e2e/` contains 1 spec (8 cases, all passing) covering platform login/verification/protected redirects/dashboard/settings/monitoring/security on mobile viewport.
- Gap: no E2E for Business registration→approval, business CRUD, billing lifecycle. Those flows are covered in-depth by backend Feature tests, but end-to-end browser journeys are not yet recorded.
- Planned: add specs in later part of Phase 10; **Must Fix Before** broad release-acceptance claims under Gate 3/5 evidence.

### F-003 — Single-worker dev server stalls (Environment, Low)
- As reproduced above; no code change. Advisory: always restart `php artisan serve` after long idle; production topology uses php-fpm.

### F-004 — Backup restore drill not evidenced (Operations, Must Fix Before Final Gate 6)
- Scripts exist and fail fast on missing env; a scripted restore verification against an isolated database has not been executed in-repo. Scheduled as part of Phase 10.12 verification.

## 6. Commands Executed (evidence log)

- `php artisan migrate:status` → 34+ recent migrations all `Ran` (no pending).
- `php artisan test` → **176 passed (842 assertions), 0 failures** (duration ~97s).
- `npm run check` (`tsc --noEmit`) → pass; `npm run lint` → pass.
- `npm run test -- --run` → 4 passed (incl. EN-fallback i18n test).
- `npm run build` → success; re-run after bundle fix → success.
- `npx playwright test` → **8 passed**.
- Live psql information-schema / pg_indexes checks (read-only, no data changed).
- Live curl smoke tests as tabled in §4 (no financial or destructive transactions).
- `git status` → clean working tree before edits (only `vite.config.ts` + docs modified).

## 7. Operational Guards Honored

No `migrate:fresh`, `migrate:refresh`, `db:wipe`, `TRUNCATE`, or `DROP` was executed. The production/`erp` database was only queried read-only. Nothing was deployed; `deploy.yml` remains approval-gated.

## 8. Suggested Next Phase Work

1. F-004 restore drill in an isolated PostgreSQL instance (Gate 6 evidence).
2. Extend E2E coverage for critical business journeys (F-002).
3. Route-level code splitting for additional initial-load reduction (Deferred).
4. Full UI-catalogue EN/MM translation review across all 100 pages (Localization completeness).
