# ERP Cloud — Prioritized Development Phases

This roadmap is based on the audit evidence, not on menu presence alone.

## Phase 1 — Critical Errors & Environment (P0/P1)

Scope: document local/prod startup, verify API/frontend environment variables, add browser smoke testing, review public health endpoint, and define a deployment readiness checklist.

Dependencies: none.

Acceptance criteria:

- Backend, frontend, PostgreSQL and cache startup commands are documented for this repository.
- Browser smoke test loads login, API health and one authorized workspace path.
- No uncaught console errors or failed required API calls on the smoke path.
- Health endpoint policy is explicit and tested.

## Phase 2 — Authentication & Workspace Access (P0/P1)

Scope: browser-verify login, email verification, invitation acceptance, no-membership onboarding, restricted memberships, multiple workspaces and platform-admin routing.

Dependencies: Phase 1.

Acceptance criteria:

- Every access state has a clear next step and no redirect loop.
- Platform admins reach only platform routes; company users reach only authorized company data.
- Tenant A cannot read Tenant B through UI or API.
- Super-admin provisioning is documented without automatic elevation.

## Phase 3 — UI/UX & Navigation (P1)

Scope: remove or implement decorative header/product controls, standardize loading/error/empty states, improve accessibility, add route-level code splitting, and verify mobile/dark mode.

Dependencies: Phases 1–2.

Acceptance criteria:

- Every visible control has a real action or an explicit disabled/unavailable state.
- All current routes pass desktop/tablet/mobile smoke checks.
- Bundle warning is reduced or accepted with a measured budget.

## Phase 4 — Organization Completeness (P1)

Scope: finish teams, organization chart editing, department edit/archive, branch/detail workflows, staff assignment and membership UI polish.

Dependencies: Phase 2; existing organization APIs and migrations.

Acceptance criteria:

- Company, branch, department, team and membership CRUD flows are complete, paginated, authorized and audited.
- Historical references prevent unsafe deletion.

## Phase 5 — CRM & Sales (P1)

Scope: customer/lead/opportunity/quotation/order/invoice/payment pages and lifecycle transitions.

Dependencies: Organization and accounting basis.

Acceptance criteria:

- Posted/approved status transitions, returns, credit notes, currency and permissions reconcile with dashboard metrics.

## Phase 6 — Products & Inventory (P1)

Scope: product/variant/barcode/catalog operations, warehouses, transfers, adjustments, stock counts, reorder alerts.

Dependencies: Organization; sales/purchasing references.

Acceptance criteria:

- No negative or duplicate stock movement; all records are tenant/company/branch scoped and auditable.

## Phase 7 — Purchasing (P1)

Scope: suppliers, RFQs, purchase requests/orders, goods receipts, supplier bills and returns.

Dependencies: Products & Inventory; accounting posting policy.

Acceptance criteria:

- Approval and receiving rules prevent duplicate/over-receiving and reconcile to inventory/accounting.

## Phase 8 — Accounting (P0/P1)

Scope: chart of accounts UI, journals, fiscal periods, posting/reversal, financial reports, reconciliation and multi-currency.

Dependencies: Sales and Purchasing source documents.

Acceptance criteria:

- Balanced journals, immutable posted entries, period controls, exact decimal arithmetic and report reconciliation.

## Phase 9 — HR & Payroll (P1)

Scope: employee records, contracts, attendance, leave, salary structures, payroll runs and payslips.

Dependencies: Organization and accounting.

Acceptance criteria:

- Payroll calculations, authorization, effective dates and audit history are complete.

## Phase 10 — POS, Projects & Assets (P2)

Scope: registers/shifts/receipts, projects/tasks/timesheets and fixed assets/depreciation.

Dependencies: Products, Inventory, Accounting and HR.

Acceptance criteria:

- Optional modules are enabled by tenant feature policy and post valid inventory/accounting events.

## Phase 11 — Reports & Automation (P2)

Scope: report catalog, parameters, exports, notifications, scheduled reports and automation controls.

Dependencies: module data and authorization model.

Acceptance criteria:

- Export visibility is tenant-scoped, progress/failure is explicit, and automation is auditable/idempotent.

## Phase 12 — Security, Testing & Deployment (P0/P1)

Scope: threat modeling, penetration testing, CI browser tests, load/performance checks, backup/restore drills, secrets and production deployment.

Dependencies: all preceding production scope.

Acceptance criteria:

- CI runs PHP/frontend/browser/security suites; tenant isolation is tested; production configuration fails closed; deployment and rollback are documented.

## Recommended next implementation

Start with **Phase 1**. Do not begin a broad ERP module build until browser verification, health-endpoint policy, runtime configuration, and workspace access smoke tests are established. After Phase 1, proceed to Phase 2 and use the existing passing authentication/workspace feature tests as the baseline.
