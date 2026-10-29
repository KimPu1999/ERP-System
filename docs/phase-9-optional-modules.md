# Phase 9 — POS, Projects and Fixed Assets

Phase 9 adds three optional, tenant-enabled modules. Each module reuses existing customers, product variants, inventory movements, employees, and accounting domain events.

## Module flags

`tenant_modules` controls `pos`, `projects`, and `fixed_assets`. Disabled modules fail closed with `404`; enabling/disabling requires the tenant organization permission. Offline POS synchronization is not implemented.

## POS

- Registers are company/branch/location scoped.
- Shifts enforce one open shift per register.
- Receipts use existing product variants and immutable stock movements.
- Cashier, cash, card, and configurable payment method records are captured per receipt.
- Receipt idempotency prevents duplicate checkout posting.
- `PosReceiptPosted` is emitted for controlled accounting consumption.

## Projects

- Projects are company scoped and may reference existing customers and employees.
- Tasks, milestones, members, timesheets, and expenses have separate tenant-owned records.
- Project and timesheet permissions are server-side.

## Fixed assets

- Asset categories and assets are company scoped.
- Acquisition and depreciation transactions are traceable.
- Straight-line depreciation uses decimal arithmetic.
- `FixedAssetCapitalized` is emitted; journal posting remains an explicit accounting mapping decision.

## API

- `GET|POST /api/v1/pos/registers`
- `POST /api/v1/pos/registers/{register}/shifts`
- `POST /api/v1/pos/registers/{register}/sales`
- `POST /api/v1/pos/shifts/{shift}/close`
- `GET|POST /api/v1/projects`
- `POST /api/v1/projects/{project}/tasks`
- `POST /api/v1/projects/{project}/timesheets`
- `GET /api/v1/fixed-assets/categories`
- `GET /api/v1/fixed-assets`
- `POST /api/v1/fixed-assets`
- `POST /api/v1/fixed-assets/{asset}/depreciate`
- `GET|PUT /api/v1/modules`
