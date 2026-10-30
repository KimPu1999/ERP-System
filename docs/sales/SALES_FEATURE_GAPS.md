# Sales Feature Gaps

| Priority | Gap | Evidence | Required next step |
|---|---|---|---|
| P1 | Customer directory and detail workflow | Phase 3 CRM routes/pages and additive schema now exist | Run database-backed company/branch and profile tests. |
| P1 | Quotation lifecycle UI | `SalesQuotationController` has transitions/conversion; no page | Add form, detail, transition, and conversion screens. |
| P1 | Sales order UI | `SalesOrderController` has create/submit/approve | Add line-item form, stock location selection, and workflow actions. |
| P1 | Delivery/invoice/payment screens | Backend endpoints exist without dedicated pages | Implement detail pages with verified state transitions. |
| P1 | Company/branch read authorization consistency | CRM endpoints enforce company/branch scope; legacy Sales endpoints still need review | Complete endpoint-by-endpoint authorization review before production use. |
| P2 | Pipeline and follow-up activities | Phase 3 data model, APIs and pages now exist | Run isolated PostgreSQL workflow tests and add configurable stages later. |
| P2 | Sales by customer/branch aggregates | Current dashboard data does not provide verified scoped aggregates | Add authoritative, currency-safe aggregate endpoints. |
| P2 | Outstanding receivables and payments-received dashboard metrics | Existing endpoints do not support all selected filters | Add company/branch/date-aware metrics with currency rules. |

No fake records or placeholder financial values were introduced.
