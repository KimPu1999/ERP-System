# Phase 0 Audit Checklist and Phase 1 Gate

## Phase 0 completed

- [x] Repository structure inspected.
- [x] Frontend, backend, migrations, routes, tests, CI, deployment, and infrastructure inspected.
- [x] Composer validation run.
- [x] PHP syntax lint run.
- [x] Laravel feature tests run.
- [x] Migration status run without changing schema.
- [x] TypeScript check, frontend lint, unit test, and production build run.
- [x] Module-by-module status classified.
- [x] Security, UI/UX, and database findings recorded.
- [x] No production data was modified.
- [x] No destructive migration/reset/wipe command was run.
- [x] Seven requested audit documents created.

## Phase 0 unresolved checks

- [ ] Re-run `composer audit` in a networked CI/release environment.
- [ ] Re-run `npm audit --audit-level=high` in a networked CI/release environment.
- [ ] Add/execute browser and responsive checks.
- [ ] Verify production deployment runner and rollback.
- [ ] Review Git repository metadata from the actual source-control checkout.
- [ ] Execute direct PostgreSQL RLS and foreign-key isolation tests.

## Phase 1 implementation gate

- [x] Production request config rejects `MAIL_MAILER=log`, debug mode, insecure cookies, localhost origin, or missing app key.
- [x] Required production configuration is validated on protected requests.
- [ ] Deployment runner consumes artifacts, safely migrates, health-checks, and records rollback. Repository gate and runner contract are now documented.
- [ ] Permission matrix covers every route and defaults to deny.
- [x] New sales/procurement transaction records have company/branch/currency/base-total fields and validation.
- [ ] Dashboard uses finalized posted/issued date rules and consistent scope. Scope is implemented; date semantics remain open.
- [x] Dashboard base currency is configured or returns an explicit unconfigured state; no silent USD business interpretation.
- [ ] Cross-tenant, cross-company, and cross-branch tests pass.
- [ ] Direct RLS tests pass for all tenant-owned tables.
- [ ] No critical/high dependency advisory remains, or an approved exception is recorded.

## Phase 1 acceptance evidence required

1. CI run URL or immutable build output.
2. Test report with backend, frontend, RLS, permission, and reconciliation suites.
3. Deployment log showing migration safety and health checks.
4. Dashboard reconciliation fixture showing tenant/company/branch/currency totals.
5. Security review sign-off for mail, CORS, cookies, secrets, platform admin, and tenant isolation.

## Phase 1 implementation result

Phase 1 is **partially implemented and verified**. Backend tests pass at 29 tests / 60 assertions, frontend checks/build pass, the forward-only migration is applied, and the local health endpoint reports database/cache success. The unchecked items above remain release blockers.

## Dashboard Phase 4 — business performance

- [x] Added authenticated GET /api/v1/dashboard/recent-transactions.
- [x] Added authenticated GET /api/v1/dashboard/top-products with ranking=sales|volume and bounded limits.
- [x] Enforced shared tenant, company, branch, date, timezone, and reports.view authorization context.
- [x] Added recent transaction coverage for posted sales invoices, valid credit notes, verified customer payments, approved/posted supplier bills, and posted journal entries.
- [x] Added net product sales and volume ranking with credit-note allocation, received-return deductions, currency metadata, and unit-aware grouping.
- [x] Replaced legacy dashboard business panels with reusable loading, empty, unavailable, retry, ranking, and transaction components.
- [x] Connected Phase 4 panels to shared URL filters and React Query refetching.
- [x] Added endpoint tests for authentication/tenant isolation, ordering, bounded results, credit-note-adjusted ranking, and unit-aware volume ranking.
- [x] Documented data-source and accounting assumptions in docs/dashboard-phase-4-business-performance-assumptions.md.
- [ ] Add verified transaction detail routes before enabling per-row View details actions.
- [ ] Add browser-level responsive/accessibility verification.

## Organization Phase 1 — company profile and overview

- [x] Added tenant-scoped company profile fields for legal identity, registration/tax details, contact/address, localization, and branding paths.
- [x] Added private company document storage with category validation, bounded file types/sizes, authenticated download, and tenant/company ownership checks.
- [x] Added GET `/api/v1/organization/overview` with active branch, department, active member, hierarchy, and recent organization activity data.
- [x] Added GET/PATCH company profile APIs plus logo/cover upload and delete endpoints.
- [x] Added organization overview, company profile, department, and staff/membership routes without implementing Branch Management Phase 2.
- [x] Preserved tenant/company authorization and fail-closed permission checks for profile changes and document operations.
- [x] Added explicit unavailable state for Teams because no Teams entity or verified source exists in the current schema.
- [x] Added feature tests for profile access, cross-tenant isolation, update validation, permission denial, overview counts, branding validation, and private documents.
- [x] Fixed `TenantContext` registration so middleware and controllers share the same request context.
- [x] Applied forward-only migrations `2026_10_29_000043` and `2026_10_29_000044` locally.
- [ ] Add browser-level responsive/accessibility verification for the organization pages.
- [ ] Implement Branch Management Phase 2 separately, including branch CRUD and branch-specific permissions.

## Organization Phase 2 — branch management

- [x] Expanded branches with operational profile, contact/address, manager, staff assignment, business hours, timezone, document prefix, status, and archive fields.
- [x] Added company-scoped unique branch codes, tenant/company indexes, manager foreign key, and forward-only migration `2026_10_29_000045`.
- [x] Added paginated branch listing with server-side search, status, city, region, manager, sorting, and bounded page sizes.
- [x] Added real branch KPI summaries for total, active, inactive, and assigned-manager counts.
- [x] Added create and edit APIs with validation, active manager qualification, staff assignment validation, and audit logging.
- [x] Added branch details and activity APIs with authorized staff, department, warehouse, manager, and lifecycle data.
- [x] Added active/inactive/archived lifecycle controls; archived branches preserve historical references and cannot be reactivated.
- [x] Added tenant/company/branch authorization and cross-tenant/cross-company isolation coverage.
- [x] Added Branch Management page, reusable summary/filter/table/form/status/details/activity components, responsive styling, and detail tabs.
- [x] Added manager and staff candidate endpoints without granting new permissions automatically.
- [x] Branch feature tests pass: 6 tests / 26 assertions; full Laravel suite passes: 56 tests / 182 assertions.
- [ ] Complete browser-level responsive/accessibility verification when the in-app browser connector is available.
- [ ] Implement Organization Phase 3 — Department Management separately.
