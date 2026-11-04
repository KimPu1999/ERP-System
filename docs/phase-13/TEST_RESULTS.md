# Phase 13 Growth Vertical — Verified Test Results

Date: 2026-11-04 (local environment only; no production staging was involved)

## Backend (Laravel / Pest)

| Command | Result |
| --- | --- |
| `cd backend && php artisan test tests/Feature/PlatformGrowthPhase13Test.php` | **9 passed** (39 assertions) |
| `cd backend && php artisan test --filter="AuthenticationAccessTest\|PlatformAdminInvitationTest\|PlatformBusinessManagementTest"` after MFA-policy fix | 55 passed (257 assertions) |
| `cd backend && php artisan test --compact` (full suite) | **220 passed, 0 failed** (1064 assertions, ~123 s) |

Covered growth-vertical test cases (`tests/Feature/PlatformGrowthPhase13Test.php`):

1. Setup-step retries deduplicate to one `onboarding_progress` row (unique `(tenant_id, step_key)`).
2. Onboarding funnel metrics and empty-cohort rate behavior (0% with an explicit denominator, not `null`).
3. Non-platform administrator receives 403 on growth endpoints.
4. Server-side filter validation (invalid period/granularity and non-UUID plan id are rejected).
5. Module "enabled" configuration never counts as recorded usage (adoption endpoint).
6. Customer-success rows are facts-only and paginated; attention reasons come from authoritative backend data only.
7. Churn is reported as `available: false`/`null` without both cancellation and renewal events — no fabricated percentages.
8. Cohort labeling uses registration window, not current state.
9. Tenant lifecycle activity outside the requested window is never exposed.

## Frontend (apps/web)

| Command | Result |
| --- | --- |
| `npm run check` (tsc --noEmit) | **passes, 0 errors** |
| `npx eslint` on changed files (`PlatformGrowthCenterPage.tsx`, `App.tsx`, `Sidebar.tsx`, `api.ts`, `i18n.tsx`, `PlatformSecuritySectionPage.tsx`) | **clean** |
| `npx vitest run` | **4 passed** (2 files) |
| `npm run build` | **succeeds** (pre-existing chunk-size warnings for ECharts/index remain) |
| `npm run i18n:check` | exits 1 **only from pre-existing unresolved keys** (`business.*`, `security.mfa*`, `platform.queue`, `common.saving`, `ops.failed`) — verified via `git stash` A/B run that these exist at `HEAD` with the growth changes removed; zero `growth.*` unresolved keys |

## Configuration checks

- `php artisan route:list` — MFA routes (`/mfa/verify`, `/auth/mfa/enroll|confirm|recovery-codes|disable`) restored; `mfa.completed` middleware alias registered; growth routes behind platform middleware.
- phpunit.xml.dist forces `APP_ENV=testing`, `APP_DEBUG=true`, `PLATFORM_MFA_REQUIRED=false`, `MAIL_MAILER=log` so the fail-closed security config stays deterministic in tests; production/staging remain fail-closed (`local`/`testing` only are honored with those overrides).

## Notes

- The full backend suite must run by itself; editing files during a run previously contaminated results.
- No PostgreSQL data was reset; all tests run against the local `erp_test` database via `RefreshDatabase`.
