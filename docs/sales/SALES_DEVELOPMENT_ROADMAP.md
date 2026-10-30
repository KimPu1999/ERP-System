# Sales Development Roadmap

## Phase 1 — Workspace foundation (completed in this change)

- Shared nested Sales layout and sticky navigation.
- Authorized company context and URL filter preservation.
- Professional overview layout using verified dashboard sources.
- Explicit unavailable states for unsupported workflows and metrics.
- Frontend checks and existing sales calculation tests.

## Phase 2 — Sales overview dashboard (implemented)

- Consolidated company/branch/date-scoped `/api/v1/sales/dashboard` endpoint.
- Server-defined KPI methodology for eligible invoiced revenue, eligible orders, verified applied payments, outstanding A/R, customers and pending deliveries.
- Revenue trend, order status, top product/customer, branch performance, receivables and recent-order panels.
- URL-preserved filters with company timezone and explicit unavailable CRM pipeline state.
- No new sales, invoice, payment, inventory or accounting records are created by dashboard reads.

Verification: `DashboardOverviewTest`, `SalesPhaseTest`, `TenantIsolationTest`, and `AuthenticationAccessTest` passed; frontend TypeScript, ESLint, Vitest and production build passed.

Remaining Phase 3 dependencies: customer/quotation/order management UI, delivery/invoice detail workspaces, payment workflow UI, returns/refunds, and dedicated Sales dashboard feature tests with seeded financial fixtures.

## Phase 3 — CRM customer, lead, opportunity and activity workspaces (implemented)

Dependencies: existing customer, quotation, product, inventory, and sales authorization services.

Acceptance criteria:

- Paginated customer directory, profile, contacts and addresses with authorized company scope.
- Lead lifecycle and transactional new/existing-customer conversion with retry protection.
- Opportunity directory and accessible Kanban pipeline with stage history.
- Follow-up activities with company-scoped completion.
- CRM dashboard and pipeline aggregates that keep forecast values separate from revenue.

Verification: frontend TypeScript, ESLint and production build passed; database-backed CRM verification is blocked until an isolated PostgreSQL service is available.

## Phase 4 — Quotation management (implemented)

- Company/branch-scoped quotation register and summary KPIs.
- Server-authoritative line pricing, fixed/percentage discounts and configured tax-code lookup.
- Approval, customer response and conversion states kept separate.
- Version snapshots, approval history, response history and printable quotation document.
- Idempotent accepted-quotation to draft Sales Order conversion without stock or accounting postings.

Verification: PHP syntax, route registration, frontend TypeScript, lint and production build passed; database-backed quotation verification remains blocked pending isolated PostgreSQL availability.

## Phase 4B — Advanced quotation operations

- Full price-list administration and conflict resolution.
- Queued customer email with delivery-state tracking.
- Secure expiring customer response links.
- Binary PDF generation, attachments and configurable approval thresholds.

## Phase 5 — Customer, quotation, and sales-order workspaces
- Quotation create/detail/transition/convert flows.
- Sales order create/detail/submit/approve flow with product, unit, tax, discount, and stock-location validation.
- No cross-tenant or cross-company records in list/detail APIs.

## Phase 3 — Fulfillment and invoicing

- Delivery detail and partial fulfillment UI.
- Invoice creation from delivered quantities.
- Returns and credit-note workflows.
- Reconciliation tests for quantities and document states.

## Phase 4 — Payments and receivables

- Payment entry, verification, allocation, and audit history.
- Currency-safe receivables reporting.
- Outstanding balance and received-payment dashboard aggregates with selected company/branch/date filters.

## Phase 5 — CRM pipeline and reporting

- Leads, opportunities, follow-up activity, customer analytics, branch performance, and dedicated sales reports.

## Phase 6 — Browser and release verification

- Desktop/tablet/mobile smoke tests.
- Keyboard and screen-reader checks.
- Full permission matrix and endpoint review.
- End-to-end order-to-cash reconciliation.
