# Phase 14 — Tenant Isolation & RBAC Audit

## Summary verdict
Tenant separation now rests on **two verified layers**:
1. **Database (new, evidence-verified)** — 166 RLS-governed tables now have `FORCE ROW LEVEL SECURITY`; policies key off connection-local settings (`app.tenant_id`, `app.platform_admin`, `app.user_email`, `app.invitation_token`, portal context).
2. **Application** — `AuthorizationService` permission checks with company/branch scoping in every controller; `ResolveTenant` middleware validates UUID + active membership + active workspace before any business route.

## Why RLS was previously not a defense
- Evidence: `pg_class.relrowsecurity = true`, `relforcerowsecurity = false`, and `tableowner = erp` (the app role). PostgreSQL silently bypasses RLS for the table owner. Empirically verified on a scratch DB: owner connection with `app.tenant_id` set to tenant B still selected tenant A's rows.
- The app was safe **only** because of app-level `where tenant_id` clauses. `RequirePlatformSuperAdmin` protected platform data, but the DB defense did not exist.

## Fix architecture (all changes app-level, no BYPASSRLS, no permission weakening)
- `2026_11_04_000001_force_tenant_row_level_security` — enables `FORCE` on every RLS-governed table (catalog-driven, schema-safe identifier quoting, reversible via `down()`).
- Context hygiene: `TenantContext` gained snapshot/restore semantics. All entry points now restore the exact prior context instead of unconditionally clearing:
  - `ResolveTenant`, `RequirePlatformSuperAdmin`, `ResolveSupplierPortalTenant` middleware
  - `InvitationController::accept/acceptToken/preview` (email/token-granted context)
  - `GenerationPlatformReportExport` job, `platform:security-prune`, `erp:purchasing-automation` commands
  - Queued jobs already retained tenant context via `SetTenantContext` middleware (verify in code `app/Jobs/Middleware/SetTenantContext.php:13-21`). No pooled-connection leakage (restore, not clear).
- Guest flows: token preview uses `app.invitation_token` connection-local grant.

## Auth platform split verified
- `users.platform_role`: not present/not required — `is_platform_super_admin` boolean plus invitation-based provisioning command (`platform:provision-admin`) and `RequirePlatformSuperAdmin` middleware. Business roles are tenant-scoped (`roles.tenant_id` nullable only for platform roles) via `role_assignments` with company/branch/department scope columns.
- No public super-admin registration: platform login enforced by `is_platform_super_admin` and business login rejects platform admins.

## Verified isolation tests
- `RlsEnforcementTest` (new): all RLS tables forced + wrong-tenant read = 0 rows + wrong-tenant INSERT = policy violation + own-tenant read works + platform context restore. **PASS (2/2)**
- `TenantIsolationTest`: cross-tenant read/write/branch-update denial, invalid UUID fail-closed, platform admin cannot self-bestow membership. **PASS (4/4)**
- Full suite under enforced RLS: see `TEST_RESULTS.md`.

## Remaining recommendations (not blocking)
- Long-term defense-in-depth: run the API under a **non-owner** DB role (owner reserved for migrations), removing reliance on FORCE alone.
- `role_assignments` duplicate-grant constraint (DATABASE_AUDIT item 5).
