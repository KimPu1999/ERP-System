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

- [ ] Production config rejects `MAIL_MAILER=log` outside local/testing.
- [ ] Required secrets/configuration are validated before boot.
- [ ] Deployment workflow deploys API and frontend, safely migrates, health-checks, and records rollback.
- [ ] Permission matrix covers every route and defaults to deny.
- [ ] Sales/procurement transaction records have explicit company/branch/currency semantics.
- [ ] Dashboard uses posted/issued date rules and consistent scope.
- [ ] Dashboard base currency is configured or returns an explicit unconfigured state; no silent USD business interpretation.
- [ ] Cross-tenant, cross-company, and cross-branch tests pass.
- [ ] Direct RLS tests pass for all tenant-owned tables.
- [ ] No critical/high dependency advisory remains, or an approved exception is recorded.

## Phase 1 acceptance evidence required

1. CI run URL or immutable build output.
2. Test report with backend, frontend, RLS, permission, and reconciliation suites.
3. Deployment log showing migration safety and health checks.
4. Dashboard reconciliation fixture showing tenant/company/branch/currency totals.
5. Security review sign-off for mail, CORS, cookies, secrets, platform admin, and tenant isolation.
