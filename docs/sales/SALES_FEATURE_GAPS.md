# Sales Feature Gaps

| Priority | Gap | Evidence | Required next step |
|---|---|---|---|
| P1 | Customer directory and detail workflow | `CustomerController` exists; no dedicated page | Build paginated customer UI with company/branch policy. |
| P1 | Quotation lifecycle UI | `SalesQuotationController` has transitions/conversion; no page | Add form, detail, transition, and conversion screens. |
| P1 | Sales order UI | `SalesOrderController` has create/submit/approve | Add line-item form, stock location selection, and workflow actions. |
| P1 | Delivery/invoice/payment screens | Backend endpoints exist without dedicated pages | Implement detail pages with verified state transitions. |
| P1 | Company/branch read authorization consistency | Some read controllers only scope by tenant | Complete endpoint-by-endpoint company/branch permission review before production use. |
| P2 | Pipeline and follow-up activities | No verified lead/opportunity/activity source | Add supported data model and APIs before UI. |
| P2 | Sales by customer/branch aggregates | Current dashboard data does not provide verified scoped aggregates | Add authoritative, currency-safe aggregate endpoints. |
| P2 | Outstanding receivables and payments-received dashboard metrics | Existing endpoints do not support all selected filters | Add company/branch/date-aware metrics with currency rules. |

No fake records or placeholder financial values were introduced.
