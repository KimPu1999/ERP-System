# Phase 14 — Executed Test Results (all runs executed locally, real PostgreSQL `erp_test`)

| # | Run | Command | Result | When |
|---|-----|---------|--------|------|
| 1 | Full backend suite (baseline, pre-fix) | `php artisan test` | **1 failed / 219 passed** (1070 assertions) — `PlatformOperationsPhaseTest › database monitoring` crash ("A non-numeric value encountered") | Phase 14 start |
| 2 | Targeted platform suites (after RoleAssignment fix) | `pest PlatformBusinessManagementTest PlatformGrowthPhase13Test` | **28 passed** (182 assertions) | after fix |
| 3 | Operations suite (after interval fix) | `pest PlatformOperationsPhaseTest` | **9 passed** (53 assertions) | after fix |
| 4 | Full backend suite (after fixes 1–2) | `php artisan test` | **220 passed** (1064 assertions) | baseline restored |
| 5 | RLS isolation suites (after FORCE migration) | `pest RlsEnforcementTest TenantIsolationTest` | **6 passed** (14 assertions) | after FORCE RLS |
| 6 | Full backend suite (under enforced RLS, pre invitation fixes) | `php artisan test` | **2 failed / 220 passed** — `AuthenticationAccessTest › pending invitations` and `StaffMembershipPhaseTest › invite & accept` (genuine RLS-dead flows surfaced by the fix) | FORCE RLS regression |
| 7 | Auth/staff/RLS suites (after invitation grant fixes) | `pest AuthenticationAccessTest StaffMembershipPhaseTest RlsEnforcementTest TenantIsolationTest` | **44 passed** | after fixes |
| 8 | **Full backend suite (final)** | `php artisan test` | **222 passed / 0 failed** (1072 assertions, 114.56 s) — includes 2 new RLS enforcement tests, run with all 96 migrations under FORCE RLS | final |
| 9 | Frontend typecheck | `npx tsc --noEmit` | clean (rc=0)` | final |
| 10 | Frontend build | `npm run build` | green; **measured glut: echarts chunk 1145 kB / index 1521 kB** (bottleneck evidence for batch 2) | Phase 14 start |
| 11 | Frontend unit tests | `npm run test -- --run` | **4 passed** (2 files) | after i18n fix |
| 12 | Frontend lint | `npm run lint` | clean | final |

## Backend suite domains covered (39 test files + 1 new)
auth/security (5), platform (9), purchasing (9), sales (4), accounting (1), inventory (2), hr (1), organization (4), workflow (1), support (1), optional modules (2), dashboard (1), + `RlsEnforcementTest`.

## Known gaps (not run in this session)
- Playwright e2e (platform-only spec exists) — not executed this session.
- Concurrency stress tests (STEP 12) — not yet written/run.
- Composer/npm dependency audit — wired in CI; not re-run here.
