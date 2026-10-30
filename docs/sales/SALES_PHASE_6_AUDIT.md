# Sales Phase 6 — Fulfillment Audit

## Existing implementation

The existing delivery endpoint combined shipment creation and physical stock posting. It did not provide fulfillment drafts, picking/packing state, tracking history, proof of delivery or delivery exceptions.

## Implemented

- Company/branch/warehouse-scoped fulfillment register and summary API.
- Confirmed-order-only fulfillment creation with line-level reserved-quantity validation.
- Picking confirmation and packing completion without inventory ledger changes.
- Reuse of the existing `delivery_orders` table as the shipment record, extended with warehouse, carrier, tracking and dispatch fields.
- Explicit dispatch endpoint with row locks, active-reservation validation, stock-movement idempotency and reservation consumption.
- Tracking event history, POD records and delivery exception records.
- Fulfillment and details pages under the existing Sales workspace.

## Controls

- Draft fulfillment, picking and packing do not decrement on-hand quantity.
- Dispatch is the only new Phase 6 operation that calls `StockMovementService`.
- Dispatch rejects quantities beyond the active reservation and returns an idempotent result for already-dispatched shipments.
- Tenant, company, branch and warehouse scope is enforced server-side.
- Existing delivery, invoice and payment workflows remain separate.

## Gaps / blockers

- Package-level tables, barcode scanning, batch/serial allocation UI, carrier callbacks, private POD file uploads and export reports are not yet implemented.
- Existing PostgreSQL feature-test execution is blocked because the configured `erp_test` PostgreSQL service is unavailable in this environment.
- The migration must be reviewed and applied in an isolated database before release.
