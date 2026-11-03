# Staging Test Results — Enterprise ERP Cloud Phase 11

Environment for all executed evidence: local macOS workstation, PHP 8.5.6 CLI, Laravel 13.39, Node 22 / npm lockfile, PostgreSQL 17 via installed server (fresh disposable `erp_test` database recreated to CI parity — CI-equivalent procedure, **dev `erp` database untouched**). Date: 2026-11-03.

> Rule of record: only actual executed results appear here. Staging-environment items that require real infrastructure (staging host, Vercel project, DNS, mail provider) are listed as PENDING, not as passed.

## 1. Backend — executed

Procedure (CI parity): drop/recreate disposable `erp_test` database → `DB_DATABASE=erp_test php artisan migrate --force` → `php artisan test`.

```
Tests:    176 passed (842 assertions)
Duration: 94.34s
Failures: 0    Errors: 0    Skipped: 0
```

Audit note: an initial run against the shared, previously used `erp_test` database produced 11 failures (165 passed) — root-caused as stale disposable-DB state (QueryException: `migrations` table mid-wipe race / duplicate `users` creation), **not** a code defect: reruns of failing files passed; the CI-parity fresh-DB procedure produced 176/176, matching the Phase 10 record (176 passed / 842 assertions).

Gate-relevant suites all green (as in Phase 10): AuthenticationAccessTest, TenantIsolationTest (+ inventory/procurement isolation), PlatformBilling/Analytics/Security/Monitoring/Settings suites, ProductionConfigurationTest, accounting/inventory/sales suites.

## 2. Frontend — executed

| Command | Result |
|---|---|
| `npm run check` | pass, 0 errors |
| `npm run lint` | pass, 0 errors |
| `npm run test -- --run` | 4 passed (2 files) |
| `npm run build` | success — `index-D06dfSq2.js` 1,415.59 kB (295.62 kB gzip), echarts chunk isolated (385.68 kB gzip), react-vendor 128.97 kB, ui-vendor 70.47 kB, CSS 399.79 kB |

## 3. E2E — executed (Phase 10 record, same day)

`npx playwright test` → **8 passed** (platform sign-in keyboard reachability, verified-email flow, protected-route redirect, live authorized dashboard, settings form mobile, analytics aggregates, monitoring truthfulness, security posture). Not re-executed in the Phase 11 window but Phase 10 evidence is same-day — recorded as such.

## 4. Live API contract — Phase 10 same-day record (identical code, no backend code changes since)

`/api/v1/health` 200; `/api/v1/me` unauth 401; platform routes unauth 401; login without CSRF 419; login with CSRF + invalid input 422; csrf-cookie header shape verified. No backend code was modified in Phase 11 (only deployment artifacts/docs added). Re-execute on staging as gates, not treated as staging bake.

## 5. PENDING — requires staging infrastructure (not claims)

| # | Pending check | Dependency |
|---|---|---|
| 1-8 | Platform login / registration / email verification / approval / invitations / business-admin login / unauthorized rejection / tenant isolation in **staging env** | staging host + Vercel project + DNS |
| 9-19 | Product / category / supplier / inventory / PO / sales / accounting / subscription / billing / analytics / export flows | staging host |
| 20 | Uploads persist across restarts | private object storage |
| 21 | Queue worker processes safe test job | Supervisor + Redis deployed |
| 22 | Scheduler runs (freshness verified) | cron + monitoring |
| 23 | Mail provider accepts messages (all 7 message types) | mail provider account approval |
| 24 | Monitoring captures real failures; 25 alerts delivered | monitoring ops setup |
| 26 | Security audit events stored in staging | staging host |
| 27-29 | EN/MM, themes, responsive in real browser across deployed SPA | frontend host |
| 30 | Backup restore drill in isolation (RPO/RTO evidence) | backup host reset |

## 6. Blockers inherited (carry-over)

- F-002 (Phase 10): no browser E2E for business-journey flows — backend coverage exists; not a launch blocker for platform shell but must be tracked.
- F-004: restore drill not executed (Blocker for Gate 6; see `BACKUP_RECOVERY_RUNBOOK.md` §4).
