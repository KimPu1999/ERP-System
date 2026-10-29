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

## Explicitly deferred to Phase 5

- [ ] Full batch/lot and serial master tracking, expiry enforcement, and global serial ledger.
- [ ] Configurable quality/quarantine release workflow and inspection policy administration.
- [ ] Receipt attachment upload/download management.
- [ ] Authorized reversal/correction workflow with compensating inventory movements.
- [ ] UOM conversion catalog and mixed-unit KPI aggregation.
- [ ] Browser-level responsive/accessibility verification.

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
