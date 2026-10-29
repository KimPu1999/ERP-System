# Dashboard Phase 2 KPI assumptions

The company dashboard uses the tenant-scoped `GET /api/v1/dashboard/overview` response.

- **Total revenue:** issued sales invoices are included; draft, cancelled, and void invoices are excluded. `base_total` is preferred, with `total` as the legacy fallback. Valid credit notes are subtracted using the linked invoice exchange rate.
- **Sales orders:** orders in the selected period are counted unless they are draft, cancelled, or void.
- **Pending invoices:** issued or partially paid invoices are counted only when `paid_total < total`. This is a current outstanding-work count rather than a period-created count.
- **Pending deliveries:** approved or partially fulfilled orders are counted only when at least one line has `ordered_quantity > delivered_quantity + returned_quantity`.
- **Inventory value:** current inventory balances are multiplied by the variant cost. Retail prices are not used.
- **Low stock alerts:** unresolved inventory alerts are counted by distinct variant within the authorized warehouse/branch scope.
- **Currency availability:** financial KPI cards are marked unavailable when the tenant has no base accounting currency. The UI does not substitute a guessed currency or value.
- **Access scope:** all dashboard aggregates require authentication, verified email, active tenant membership, and `reports.view`; company and branch filters are validated server-side.

Operational counts such as pending invoices, pending deliveries, inventory value, and low-stock alerts represent current state. Revenue and sales-order counts honor the selected date range.
