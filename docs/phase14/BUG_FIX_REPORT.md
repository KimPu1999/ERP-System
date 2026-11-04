# Phase 14 — Bug Fix Report

Each fix: reproduction evidence → root cause → change → verification.

---

## ERP-P0-1 — Tenant RLS silently bypassed by the application role (SECURITY)
- **Evidence:** `pg_class`: `relrowsecurity=t, relforcerowsecurity=f, tableowner=erp` (app role). Scratch-DB experiment: tenant A rows visible to owner posing tenant B; with FORCE: tenant B sees 0, tenant A sees 1, `platform_admin=true` sees 1.
- **Risk:** DB-level tenant leak; app-level scoping was the only barrier (P0).
- **Files:** `database/migrations/2026_11_04_000001_force_tenant_row_level_security.php` (new).
- **Verification:** new `RlsEnforcementTest` (read/write/insert/tenant-A/platform-context assertions) passed; full backend suite passed under enforced RLS; `TenantIsolationTest` unchanged and passing.

## ERP-P1-1 — Platform businesses list TypeError (31 log occurrences)
- **Evidence:** `storage/logs/laravel.log:329+` — `TypeError ... Argument #1 ($assignment) must be of type App\Http\Controllers\Api\V1\RoleAssignment, App\Models\RoleAssignment given ... PlatformTenantController.php:196`; file mtime predates log times, so unfixed.
- **Root cause:** missing model import; PHP resolved the type-hint to a nonexistent class in the controller namespace.
- **Fix:** added `RoleAssignment` to the `App\Models\` import group (`PlatformTenantController.php:4`).
- **Verification:** `PlatformBusinessManagementTest` + `PlatformGrowthPhase13Test` (28 tests) passed.

## ERP-P1-2 — Operations DB monitor crash ("A non-numeric value encountered")
- **Evidence:** full-suite run pre-fix: `1 failed, 219 passed` — `PlatformOperationsPhaseTest › keeps database monitoring strictly read-only`, at `OperationsController.php` slow-query mapping.
- **Root cause:** `now() - query_start` PostgreSQL interval returns as string; multiplied by 1000 in PHP.
- **Fix:** compute milliseconds in SQL (`ROUND(EXTRACT(EPOCH ...)*1000)`); robust cast in PHP.
- **Verification:** test passes; full suite afterwards `220 passed` (baseline restored).

## ERP-P1-3 — Public invitation preview 404 under enforced RLS (surfaced by P0-1 fix)
- **Evidence:** `AuthenticationAccessTest` regressed after FORCE RLS (`company_name null`); static read of `InvitationController::preview`: guest read of `invitations` matched no policy condition.
- **Root cause:** invitations policy allowed only platform/tenant/invitee-email grants — token-based guest access impossible.
- **Fix:** migration `2026_11_04_000002_invitation_token_preview_access_rls` adds `token_hash = current_setting('app.invitation_token')` grant; controller sets/clears the setting for the single lookup, then grants bounded tenant context for the summary load.
- **Verification:** AuthenticationAccessTest 32/32 passed.

## ERP-P1-4 — User workspace access missing company/tenant on pending invitations (surfaced by P0-1 fix)
- **Root cause:** eager `company` load ran without any context the invitee is entitled to.
- **Fix:** `WorkspaceAccessController` loads summaries under per-invitation bounded tenant context; snapshot/restore hygiene.
- **Verification:** included in AuthenticationAccessTest suite (passed).

## ERP-P2-1 — Missing i18n keys (EN/MM)
- **Evidence:** `t('ops.failed')`, `t('platform.queue')`, `t('common.saving')`, and six `security.mfa*` keys absent from both catalogs → raw key labels rendered even in English.
- **Fix:** nine keys added to `apps/web/src/lib/i18n.tsx` in English **and** Burmese.
- **Verification:** `tsc --noEmit` clean (rc=0), vitest 4/4 passed.

## ERP-P2-2 — Context hygiene (clear vs. restore)
- **Fix:** middleware, jobs, and commands snapshot/restore exact prior context (leak prevention across pooled connections; STEP 7 requirement).
- **Files:** `TenantContext.php`, `ResolveTenant`, `RequirePlatformSuperAdmin`, `ResolveSupplierPortalTenant`, `GeneratePlatformReportExport`, `routes/console.php`.
- **Verification:** full suite green; job/job middleware already restored (code-read evidence).

---

## Previously reported issues (re-verified against current code)
- `users.platform_role` missing → superseded design (`is_platform_super_admin`); no repro.
- Duplicate supplier index → already reconciled forward-only (2026-11-02); no live duplicate.
- Supplier/product/category creation failures → no current reproductions (suppliers/sales/catalog suites green in full run).
- Laravel API connectivity/auth failures → current env uses cookie+CSRF+`X-Tenant-Id`; auth suites green.
- `Undefined variable $policy` (AuthController:47, ×4 old log) and `Undefined array key` (PlatformGrowthService:365) → fixed in tree before Phase 14 by prior commits; covered by currently-passing suites.

## Deliberate non-fixes (with rationale)
- `.env.conditions` etc.: see PERFORMANCE NOTES in FINAL_REPORT (echarts/index chunk splitting deferred — measurable baseline recorded, code-split is a change with UI regression risk requiring its own verification cycle).
- Placeholder `main.test.tsx`, checker false-negatives, dead `PlatformSettingsController`/`CompanyPolicy` — queued for batch 2 cleanup (P3).
