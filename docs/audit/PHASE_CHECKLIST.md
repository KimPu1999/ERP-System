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
