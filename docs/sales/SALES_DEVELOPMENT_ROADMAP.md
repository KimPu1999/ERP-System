# Sales Development Roadmap

## Phase 1 — Workspace foundation (completed in this change)

- Shared nested Sales layout and sticky navigation.
- Authorized company context and URL filter preservation.
- Professional overview layout using verified dashboard sources.
- Explicit unavailable states for unsupported workflows and metrics.
- Frontend checks and existing sales calculation tests.

## Phase 2 — Customer, quotation, and sales-order workspaces

Dependencies: existing customer, quotation, product, inventory, and sales authorization services.

Acceptance criteria:

- Paginated customer directory with authorized company scope.
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
