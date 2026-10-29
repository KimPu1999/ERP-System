# Sales Workspace Audit — Phase 1

## Scope

Audited the CRM & Sales frontend route, API client, Laravel routes/controllers, sales models, existing dashboard aggregates, authorization usage, and the documented order-to-cash workflow.

## Findings

| Area | Evidence | Status | Finding |
|---|---|---|---|
| Sales route | `apps/web/src/App.tsx`, `/crm-sales` | Partial | A single summary page existed without a shared Sales layout or persistent navigation. |
| Sales overview data | `DashboardController`, `GET /api/v1/dashboard/overview` | Implemented but unverified | Provides scoped revenue, orders, pending invoice/delivery counts, charts, recent sales, and product rankings. |
| Customers | `CustomerController`, `GET/POST /api/v1/customers` | Partial | Backend list/create exists; no dedicated frontend directory or detail workflow. |
| Quotations | `SalesQuotationController` | Partial | Backend transitions and conversion exist; no dedicated frontend page. |
| Sales orders | `SalesOrderController` | Partial | Backend create/submit/approve exists; no frontend order form/table. |
| Deliveries | `DeliveryOrderController` | Partial | Backend fulfillment posting exists; no frontend delivery workspace. |
| Invoices | `SalesInvoiceController` | Partial | Backend issue/print exists; no frontend invoice workspace. |
| Payments | `PaymentController` | Partial | Backend verification/allocation exists; no frontend payment workspace and dashboard aggregate is not filter-complete. |
| Returns/refunds | `SalesReturnController` | Partial | Backend return posting exists; no frontend workflow. |
| Sales reports | Dashboard analytics and general workflow/report routes | Partial | Verified dashboard analytics exist; dedicated sales report UI is not available. |
| Authorization | `AuthorizationService`, sales write controllers | Implemented but unverified | Write paths use `sales.manage`/`sales.approve`; several read endpoints are tenant-scoped but do not yet perform full company/branch permission checks. |
| Accounting/inventory effects | `docs/phase-6-sales.md` and sales services | Implemented but unverified | Document transitions, stock reservation/delivery, payment allocation, and accounting events are documented; end-to-end browser workflows are not verified. |

## Phase 1 conclusion

The Sales workspace now has a nested shell, persistent navigation, authorized company context, verified dashboard data integration, and explicit unavailable states. New sales business workflows are intentionally deferred.
