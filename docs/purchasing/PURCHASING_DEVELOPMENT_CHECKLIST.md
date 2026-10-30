# Purchasing Development Checklist

## Phase 3 — Purchase Order Approval & Authorization

- [x] Reused the existing generic approval definitions, steps, requests, actions, and notification tables.
- [x] Added approval-cycle metadata, policy snapshots, currency/effective-date fields, and purchase-order approval status.
- [x] Added sequential approval levels with server-side amount and currency matching.
- [x] Added fail-closed behavior when no active policy or eligible approver exists.
- [x] Added approve, reject, request-revision, return-to-draft, and resubmission transitions.
- [x] Added row locking and duplicate/stale decision protection.
- [x] Added self-approval prevention and active company-membership checks.
- [x] Added append-only approval action history with policy version and correlation ID.
- [x] Added approval inbox, filters, summaries, pagination, and decision controls.
- [x] Added purchase-order detail approval history and decision panel.
- [x] Added queued in-app approval notifications after transaction commit.
- [x] Added feature coverage for multi-level approval, revisions, missing policies, self-approval, notification creation, tenant scope, and no stock movement.
- [x] Frontend typecheck, lint, unit test, and production build pass.
- [x] Full Laravel suite passes before Phase 4: 73 tests / 285 assertions.

## Phase 4 — Goods Receiving & Warehouse Integration

- [x] Added additive Goods Receipt scope, warehouse, receipt-date, posting, inspection, batch, and serial metadata fields.
- [x] Separated draft creation/editing from explicit posting; drafts never create stock movements.
- [x] Added authorized Goods Receipt list, create, update-draft, detail, post, movement, receiving-summary, receivable-PO, location, and print endpoints.
- [x] Validated approved/partially-received PO eligibility, active company/branch/warehouse/location scope, and storage-location ownership.
- [x] Added decimal-safe accepted, rejected, and damaged quantity capture; only accepted quantities post to the immutable inventory ledger.
- [x] Added row locking, over-receiving protection, idempotent posting, posted-document immutability, and PO receiving-state updates.
- [x] Added serial uniqueness validation for posted receipt history and optional batch/serial fields.
- [x] Added Goods Receipts workspace navigation, dashboard/table, draft form, detail/posting page, movement history, and tenant-aware print output.
- [x] Added Phase 4 feature tests for draft isolation, accepted-vs-rejected quantities, partial/full receiving, over-receiving, idempotency, and posted immutability.
- [x] Frontend typecheck, lint, unit test, and production build pass.
- [x] Full Laravel suite passes: 76 tests / 299 assertions.

## Phase 5 — Supplier Bills, Accounts Payable & Payments

- [x] Added additive supplier-bill fields for company/branch, invoice/accounting/due dates, currency, totals, approval, posting, and accounting references.
- [x] Added supplier bill lines and auditable 2-way/3-way matching results against purchase-order and posted goods-receipt quantities.
- [x] Added draft, submit, approve, and post lifecycle with server-side recalculation, duplicate supplier-invoice protection, and posted-document safeguards.
- [x] Added explicit company `accounts_payable` mapping and open-period checks; bill posting fails closed when accounting configuration is missing.
- [x] Added separate supplier payment records, idempotent AP settlement journal posting, payment allocation locking, and over-allocation protection.
- [x] Added AP register with posted-bill-only liabilities, payment allocations, supplier credits, currency-aware output, and aging buckets.
- [x] Added supplier credit-note draft/post workflow with accounting mapping and immutable source-bill preservation.
- [x] Added Supplier Bills, Accounts Payable, Payments, and Supplier Credits pages with filters, real API data, loading/error/empty states, and posting-rule explanations.
- [x] Added Laravel feature coverage for bill lifecycle, duplicate invoice rejection, accounting posting, payment posting, tenant/company authorization, and allocation limits.
- [x] Frontend typecheck, lint, and production build pass.
- [x] Full Laravel suite passes: 78 tests / 317 assertions.

## Explicitly deferred to later phases

## Phase 6 — Purchase Returns, RTS & Procurement Analytics

- [x] Added receipt-anchored purchase return cases with company/branch scope, decimal quantity validation, partial-return protection, and source batch/serial preservation.
- [x] Separated draft, approval, and return shipment states; draft/approval never deduct inventory.
- [x] Added idempotent authorized dispatch through the existing immutable inventory ledger with row locking and negative-stock protection.
- [x] Added return list/detail APIs, approval actions, shipment records, supplier debit-note drafts, and manually verified supplier refund records.
- [x] Added supplier credit-note linkage field without treating buyer debit notes or unverified credits as settlement.
- [x] Added supplier performance and procurement analytics APIs with explicit unavailable-metric and multi-currency policies.
- [x] Added Purchase Returns management and detail UI with KPI cards, filters, create draft, approval, and dispatch controls.
- [ ] Full quality/quarantine release workflow, credit allocation/reversal UI, supplier refund accounting posting, configurable scorecard administration, report export UI, and browser-level accessibility verification remain open.

- [ ] Full batch/lot and serial master tracking, expiry enforcement, and global serial ledger.
- [ ] Configurable quality/quarantine release workflow and inspection policy administration.
- [ ] Receipt attachment upload/download management.
- [ ] Authorized reversal/correction workflow with compensating inventory movements.
- [ ] UOM conversion catalog and mixed-unit KPI aggregation.
- [ ] Browser-level responsive/accessibility verification.
- [ ] Supplier bill/payment attachment storage and private download management.
- [ ] Configurable bill approval policies and matching tolerance administration UI.
- [ ] Multi-currency exchange-rate conversion for AP aggregation and allocations.
- [ ] Supplier payment verification/authorization queue and provider integrations.
- [ ] Full supplier credit allocation/reversal history and credit-note line-item workflow.

## Phase 7 — Purchasing Settings, Automation & Production Security

- [x] Audited and reused existing approval, notification, automation, queue, audit, role, and tenant-context services.
- [x] Added company-scoped Purchasing Settings for general defaults, receiving controls, and invoice-matching tolerances.
- [x] Added atomic company numbering policies with database row locking, uniqueness, previews, and legacy fallback for unscoped documents.
- [x] Added Purchasing Settings UI with General, Numbering, Receiving & Matching, Automation, Notifications, and Security & Audit tabs.
- [x] Added tenant RLS policies for new settings, numbering, and notification-preference tables.
- [x] Added bounded hourly automation dispatch, idempotent execution records, retries, backoff, failure redaction, and dry-run API.
- [x] Added company-scoped automation metadata, notification preferences, audit-log filtering, and server-side authorization checks.
- [ ] Phase 7-specific feature tests, email provider delivery confirmation, browser responsive/dark-mode verification, and isolated restore testing remain open.

## Explicitly deferred to Phase 4

- [ ] Provider-backed email notifications and delivery observability.
- [ ] Approval delegation UI and temporary approver assignment management.
- [ ] Full approval-policy administration UI.
- [ ] Goods receipt, supplier billing, and payment effects.
- [ ] Browser-level responsive/accessibility verification.
- [ ] Advanced approval reminders and escalation schedules.

## Safety notes

- Approval never posts inventory, accounting, supplier bills, or payments.
- Historical approval cycles remain stored when an order is revised and resubmitted.
- The legacy `procurement_approvals` table remains for compatibility; new Purchase Order approval cycles use the shared generic approval engine.
- Receipt quantities are stored with decimal precision. Because the current schema has no UOM conversion or tracking master tables, mixed-unit dashboard totals are returned as unavailable rather than summed.
