# Phase 14 — Final Enterprise ERP Audit, Integration, QA & SaaS Optimization

**Status: audit in progress — first evidence-based batch delivered.**
Environment audited: local macOS dev machine (PHP 8.5.6, Laravel, PostgreSQL 17 local, React 19/Vite web app), isolated `erp_test` PostgreSQL test database.

## Scope executed so far
- Step 1:Repository audit via four parallel deep inspections (backend routes/models/services/tests, DB migrations, frontend, infrastructure/CI).
- Step 2: Defect register built from actual `storage/logs/laravel.log` failures plus audit findings.
- Batch 1 fixes applied and verified with real test runs (see `BUG_FIX_REPORT.md`, `TEST_RESULTS.md`).
- Release gate status: see `RELEASE_GATES.md`.

## Verified defect register (prioritized)

| ID | Module | Severity | Status | Root cause / evidence |
|----|--------|----------|--------|----------------------|
| ERP-P0-1 | Tenant RLS | **P0 (security)** | **FIXED, test-verified** | All 100+ RLS policies were silent no-ops for the application because the DB role (`erp`) owns the tables and `FORCE ROW LEVEL SECURITY` was never enabled. Empirically proven: owner connection posing as tenant B still saw tenant A rows before the fix. |
| ERP-P1-1 | Platform businesses list | **P1** | FIXED, test-verified | `PlatformTenantController` type-hinted closure param `RoleAssignment` without importing the model; PHP resolved it to nonexistent `App\Http\Controllers\Api\V1\RoleAssignment` → TypeError ×31 in log; broke tenant payload rendering. |
| ERP-P1-2 | Platform operations → DB monitor | **P1** | FIXED, test-verified | `OperationsController::database()` multiplied a PostgreSQL `interval` string by 1000 (non-numeric PHP warning → 500 in tests). Full suite run had this exact failure (01 failed / 219 passed before fix). |
| ERP-P1-3 | Auth login `MFA` path | P1 (historical) | Already fixed before phase 14; kept unverified until suite run | `Undefined variable $policy` ×4 (log 03:11). Current code passes `SecurityPolicy` correctly. |
| ERP-P1-4 | Platform growth — customer success table | P1 (historical) | Already fixed; suite re-verifies | `PlatformGrowthService.php:365` unguarded collection `offsetGet`. Current code uses `->get()`. |
| ERP-P2-1 | i18n missing keys | P2 | FIXED | `ops.failed`, `platform.queue`, `common.saving`, `security.mfaRequired/ActionNeeded/SignInAgain/ScanStep/VerifyStep/Restart` existed in neither English nor Burmese catalogs; ops page rendered the literal key `ops.failed`. Both catalogs extended; TS + vitest re-run clean. |
| ERP-P2-2 | Background context hygiene | P2 | FIXED | Job middleware restored tenant context, but HTTP/platform middleware unconditionally **cleared** it (cross-connection leakage risk, STEP 7 requirement). All entry points now snapshot/restore exact previous state. |
| ERP-P3-1 | Schema redundancy | P3 | OPEN | Duplicate composite indexes on `stock_movements` and `role_assignments`; widespread unindexed non-tenant FKs (`journal_lines.journal_entry_id` critical). |
| ERP-P3-2 | i18n type drift | P3 | OPEN (partially fixed) | ~1500 hardcoded UI literals lack translations; `as never` casts scattered; checker script misses `business.*` block (false negatives). |
| ERP-P3-3 | Repo hygiene | P3 | OPEN | `backend/mfa-repro-seed*.php` contain hardcoded test passwords; one no-op placeholder unit test on frontend (`main.test.tsx`). |
| ERP-P3-4 | Infra | P3 | OPEN | No `FORCE` cron for backups; nginx lacks gzip; Dockerfile uses `artisan serve` + PHP 8.3 vs CI PHP 8.4; deploy.yml does not deploy (external runner contract); ~14 env keys undocumented in `.env.example`. |

## Previously reported issues — re-verified against current code
- Missing `users.platform_role`: **not an issue** — platform distinction uses `users.is_platform_super_admin` + explicit platform admin invitation table.
- Duplicate supplier migration index: reconciled by forward-only `2026_11_02_000001_reconcile_supplier_invoice_indexes`.
- Supplier/product/category creation failures, catalog reference prerequisites, inventory errors, Laravel connectivity/auth failures: **no current reproductions found**; full backend suite (220+ tests incl. supplier/sales/inventory/auth suites) passes against real PostgreSQL — see `TEST_RESULTS.md`.

## Execution state
Batch 1 (P0 + P1 + context hygiene + i18n) is complete with regression evidence.
Batch 2 candidates (indexed FKs, dead-code removal, `.env.example` completion, frontend bundle splitting, per-route business permission audit) are proposed in `BUG_FIX_REPORT.md`.
