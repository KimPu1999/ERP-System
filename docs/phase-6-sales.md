# Phase 6 — CRM and Sales

The tenant-scoped order-to-cash workflow is:

`quotation → accepted quotation → sales order → approval/reservation → delivery → invoice → verified payment allocation`

Sales totals use BCMath decimal strings. Payments start as `pending_verification`; only verified payments can update invoice balances. Deliveries and returns use immutable stock movements. Document transitions are recorded in `sales_document_history`, and downstream records retain their source IDs.

## API surface

- `GET|POST /api/v1/customers`
- `POST /api/v1/customers/{customer}/contacts`
- `GET|POST /api/v1/sales/quotations`
- `POST /api/v1/sales/quotations/{quotation}/transition`
- `POST /api/v1/sales/quotations/{quotation}/convert`
- `GET|POST /api/v1/sales/orders`
- `POST /api/v1/sales/orders/{order}/submit`
- `POST /api/v1/sales/orders/{order}/approve`
- `POST /api/v1/sales/orders/{order}/deliveries`
- `POST /api/v1/sales/orders/{order}/invoices`
- `POST /api/v1/sales/orders/{order}/returns`
- `GET|POST /api/v1/sales/payments`
- `POST /api/v1/sales/payments/{payment}/verify`
- `POST /api/v1/sales/payments/{payment}/allocate`
- `GET /api/v1/sales/analytics/customer-balances`
- `GET /api/v1/sales/invoices/{invoice}/print`

Accounting journal posting is intentionally not performed; documented domain-event consumers can be added in the accounting phase.
