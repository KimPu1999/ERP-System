# Revised Development Roadmap

The roadmap is ordered by dependency. Do not begin broad feature expansion until Phase 1 acceptance criteria pass.

## Phase 1 — Release, security, and scope hardening (P0/P1)

Dependencies: none.

Work:

- Make production mail, cookie, CORS, queue, cache, storage, TLS, and secret configuration explicit and environment-validated.
- Replace the deployment handoff with a real controlled deployment or document and test the external runner contract.
- Build a route-to-permission matrix and default-deny tests for all tenant/platform endpoints.
- Fix company/branch/currency scope in sales, purchasing, and dashboard queries.
- Add direct RLS and cross-tenant tests.

Acceptance criteria:

- Production startup rejects insecure mail/log and missing required secrets.
- A release deploys API and frontend, runs safe migrations, passes health checks, and has rollback evidence.
- Unauthorized users receive 403/404 consistently for every protected read/write path.
- Dashboard totals reconcile by tenant, company, branch, date semantics, and base currency.
- Direct RLS tests pass for all tenant-owned tables.

Tests: backend feature/security suite, PostgreSQL RLS integration tests, permission matrix tests, dashboard reconciliation tests, deploy smoke test.

## Phase 2 — Authentication and tenant administration completion (P1)

Dependencies: Phase 1.

Work: finish password reset token UI, email verification browser flow, MFA recovery/session controls, tenant/company/branch lifecycle, role assignment UX, and audit log review.

Acceptance criteria: registration → verification → onboarding → tenant selection → role-scoped access works in browser; reset and MFA flows work; no cross-tenant object access; all admin actions audited.

Tests: browser auth suite, expired/signed-link cases, session revocation, tenant-switching and branch-scope tests.

## Phase 3 — Core catalog and inventory operations (P1)

Dependencies: Phases 1–2.

Work: complete product/category/barcode UI, CSV import/export, warehouse/branch scope, stock counts, transfer and reorder workflows.

Acceptance criteria: CRUD and import/export are functional, validated, authorized, idempotent, and reconcile inventory balances to movements.

Tests: CSV validation, ledger reconciliation, concurrency/idempotency, permission, mobile browser tests.

## Phase 4 — Procurement and sales lifecycle (P1)

Dependencies: Phases 1–3.

Work: complete supplier/PO, CRM/quote/order/delivery/invoice/payment/return flows and connect inventory/accounting posting rules.

Acceptance criteria: state transitions are valid; cancelled/returned documents do not inflate reports; company/branch/currency is preserved; posting is auditable.

Tests: lifecycle integration, financial reconciliation, tenant/company/branch isolation, approval paths.

## Phase 5 — Accounting and finance production controls (P1)

Dependencies: Phase 4.

Work: chart setup, fiscal periods, journals, taxes, bank/payment reconciliation, multi-currency reporting, financial report exports.

Acceptance criteria: balanced journals, closed-period protection, reversible postings, base-currency conversion, and report reconciliation to source documents.

Tests: accounting property tests, exchange-rate cases, close/reopen authorization, audit log checks.

## Phase 6 — HR/payroll, approvals, notifications, reports (P2)

Dependencies: Phases 1–5.

Work: complete HR/payroll UI and approval routing; make report exports and notifications observable and retryable.

Acceptance criteria: payroll arithmetic and approval state are auditable; exports complete or fail visibly; notification delivery has retry/dead-letter handling.

Tests: payroll edge cases, approval segregation-of-duties, queue retry, export download/access tests.

## Phase 7 — POS, projects, fixed assets, localization (P2)

Dependencies: Phases 1–6.

Work: complete dedicated UIs, optional-module lifecycle, POS offline/sync design if required, projects/assets, settings, English/Burmese localization.

Acceptance criteria: each module has a real route/API/UI flow, permission model, audit trail, responsive states, and test coverage; localization has translated UI and date/number/currency formatting.

## Phase 8 — Observability and operational maturity (P2/P3)

Dependencies: Phase 1 and production deployment.

Work: move system health diagnostics to authorized platform monitoring, add metrics/log correlation/alerts, backup restore drills, accessibility and visual regression coverage.

Acceptance criteria: platform-only health access, alerting with ownership, measured SLOs, restore evidence, and a repeatable release checklist.

## Exactly what should be implemented next

**Phase 1, starting with transaction scope and production security configuration.** The immediate implementation ticket should be: “Make sales/procurement/dashboard company, branch, currency, and posting-date semantics explicit; enforce endpoint permissions; and make the deployment/mail configuration release-safe.”
