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
- [x] Full Laravel suite passes: 73 tests / 285 assertions.

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
