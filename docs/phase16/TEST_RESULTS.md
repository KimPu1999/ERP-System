# Phase 16 — Test Results

All results below are from commands actually executed on 2026-11-04. Nothing in this file is projected or assumed.

## Backend (Pest)

Command: `cd backend && ./vendor/bin/pest`

```
Tests:    237 passed (1182 assertions)
Duration: 125.62s
```

Includes the Phase 16 suite `tests/Feature/ImprovementPhase16Test.php` — 15 tests covering:

- feedback submission, tenant scoping, status transitions (422 on illegal jumps), duplicate closure with audit trail, note visibility, conversion to request/defect
- feature request lifecycle, effort-estimate policy, priority-change reason enforcement
- roadmap visibility rules and the release-evidence gate on `released`
- defect workflow gates (verify requires passing retest; close requires root cause)
- rollout stage-approval gates, kill switch, tenant cohort assignment
- release lifecycle, deploy-without-evidence rejection, publish-without-notes rejection
- SLO evaluation from observations (meeting/violated/unknown), error budget
- satisfaction gating (only after resolution, one per user)
- maturity evidence-length validation
- business endpoints returning only published/customer-visible data
- cross-tenant denial for business feedback endpoints

The full 237-test run also confirms **no regressions** in Phases 1–15 suites.

## Frontend

| Check | Command | Result |
|---|---|---|
| Typecheck | `npm run check` | Clean (0 errors) |
| Lint | `npm run lint` | Clean (0 errors, 0 warnings) after removing unused imports |
| Unit tests | `npx vitest run --config vitest.config.ts` | 2 files, 4 tests passed (main.test.tsx, i18n.test.ts) |
| i18n parity | `npm run i18n:check` | Exit 0 — EN/MM parity holds for all `improvement.*` and `nav.improvement*` keys; remaining unresolved keys listed are pre-existing, not Phase 16 keys |
| Production build | `npm run build` | Success (`tsc -b && vite build`, built in ~13s). Pre-existing chunk-size warnings only (echarts vendor chunk) |

Note: `npm run test` runs vitest in watch mode; the CI-equivalent one-shot run is `npx vitest run --config vitest.config.ts`.

## Issues found and fixed during testing

1. **Pre-existing bug (not Phase 16)**: `ResolveTenant.php` middleware `finally` block masked the original controller exception when the tenant context was restored. Fixed to rethrow the original error. This was hiding real 500-root-causes behind misleading "tenant missing" errors.
2. New Phase 16 tables initially missing `FORCE ROW LEVEL SECURITY` — caught by the RLS regression suite, fixed in the migration.
3. `deployed_at` NOT NULL constraint conflicted with the "not yet deployed" state — made nullable in migration `100002`.

## Not executed

- Playwright e2e suite (`npm run e2e`) was not run in this session — it requires a running full stack. No e2e result is claimed.
- No CI pipeline run was available; CI ingestion into quality metrics remains `not_configured` by design.
