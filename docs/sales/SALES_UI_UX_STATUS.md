# Sales UI/UX Status

## Implemented

- Shared `SalesWorkspaceLayout` with nested React Router outlet.
- Sticky Sales navigation below the 64px global header.
- Horizontal scrolling navigation on narrow screens.
- Company-context query preservation through Sales navigation.
- Responsive Sales Overview with compact KPI cards, filters, analytics cards, tables, loading skeletons, empty states, error states, and unavailable states.
- Real dashboard data used for recognized revenue, sales orders, pending invoices, pending deliveries, recent sales activity, and product rankings.
- Unsupported destinations are rendered as disabled planned items instead of misleading links.

## Not implemented

- Customer, quotation, order, delivery, invoice, payment, return, and sales-settings screens.
- Sales-specific customer, branch, and pipeline aggregate endpoints.
- Browser-level visual/accessibility verification.
