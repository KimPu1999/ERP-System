# OPERATIONS_TEST_RESULTS.md — Phase 12

All results below are from actual runs on 2026-11-03. Nothing is projected.

## Backend (PostgreSQL-backed feature tests)

Command: `DB_DATABASE=erp_test php artisan test` (fresh migration of disposable `erp_test` database; neither the dev database `erp` nor any production data was touched).

```
Tests:    194 passed (934 assertions)
Duration: 105.98s
```

New Phase 12 test files (run together as `php artisan test tests/Feature/SupportTicketsPhaseTest.php tests/Feature/PlatformOperationsPhaseTest.php` → **18 passed, 92 assertions**):

- `SupportTicketsPhaseTest`
  - unique ticket numbers + lifecycle defaults
  - unsafe attachment types rejected (mime allowlist)
  - attachment authorized download
  - cross-tenant ticket access denied (list/show/reply/close → 404/empty)
  - valid status transitions; resolution notes required before resolve
  - internal notes invisible to business thread, visible to platform
  - first-response auto-recording + audited assignment
  - SLA on-track/breached/not_configured policy behavior
  - platform support APIs behind platform admin (401/403/200)
- `PlatformOperationsPhaseTest`
  - overview built from verified data; explicit not_configured markers (release, backups, worker heartbeat)
  - backup evidence lifecycle (not_configured → reported; registered ≠ restore-verified)
  - multi-currency cost sums stay separated (USD vs MMK; actual vs estimate)
  - maintenance requires authorization and valid transitions (planned→in_progress rejected; approval fields recorded)
  - database monitoring bounded and read-only
  - queue facts without fabricated worker heartbeat
  - monitoring alert fingerprint dedupe (unique constraint enforced) + read-only incident list
  - manual release records; `not_configured` before any record
  - SLA policy create 403 for business users; maintenance notice stays owned by Phase 9 settings endpoint

Also re-ran safe inspection commands: `php artisan migrate:status` (all Ran after applying
the forward-only migration to the dev DB `erp`), `php artisan route:list` (all new
routes registered inside `verified`+`platform.admin`), `php artisan schedule:list`
(4 jobs), `php artisan queue:failed` (none).

## Frontend

```
npm run check (tsc --noEmit)   → clean (0 errors)
npm run lint (eslint .)        → clean (0 errors)
npm run test -- --run (vitest) → 4 passed (2 files)
npm run build                  → ✓ built in 11.65s (chunk-size warning only)
```

i18n check: both `en` and `my` dictionaries now contain identical key sets for all new `ops.*` / `support.*` strings (3 missing Burmese keys were found and added during the run).

## Not executed / not claimable

- No staging E2E (Playwright) run — no staging environment exists.
- No production readiness claims of any kind.
