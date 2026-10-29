# ERP Cloud — Missing Features

These are missing or materially incomplete capabilities found from source inspection. “Missing” means there was no evidence of a complete end-to-end UI/API workflow; an existing model or route alone was not counted as completion.

| Module | Missing or incomplete capability | Evidence | Priority |
|---|---|---|---|
| Platform Console | Full tenant lifecycle actions, platform user administration, and audited super-admin provisioning workflow | `apps/web/src/pages/TenantManagement.tsx`, `PlatformTenantController`, no setup command/seed | P1 |
| Platform Billing | Complete provider-backed subscription checkout, webhook reconciliation UI, and plan mutation workflow | `PlatformControlPage.tsx`, `PlatformBillingController` are primarily read/report surfaces | P2 |
| Roles & Permissions | Access-review workflow, effective-date assignment UI, and broader browser coverage | `RoleManagementPage.tsx` now provides role editing, backend-backed permission matrix, and assignment management; access reviews remain unavailable | P1 |
| Organization | Complete organization-chart editor and team management UX | `OrganizationPage.tsx`; teams metric can return unavailable; no dedicated team page | P1 |
| Departments | Business Unit integration and browser-level verification | Department CRUD, hierarchy, status, detail/activity, manager validation, filters, and pagination are now implemented; Business Units have no verified source | P1 |
| Products | Product create/edit/detail, variant management, barcode management, CSV import/export | `ProductCatalogPage.tsx` only lists variants; Import/Export buttons have no handlers | P1 |
| Inventory | Stock operation forms, warehouse/location management UX, transfers, adjustments, counts and audit views | `InventoryPage.tsx` only reads balances/report; backend controllers exist | P1 |
| Purchasing | Full supplier, RFQ, purchase request/order, receiving and bill lifecycle pages | `PurchasingPage.tsx`, `SuppliersPage.tsx` are summary/list surfaces | P1 |
| CRM & Sales | Complete customer/lead/opportunity/quotation/order/invoice/payment detail and transition UI | `CrmSalesPage.tsx` is a consolidated summary; backend APIs are broader | P1 |
| Accounting | Journal entry, period close, financial report and reconciliation UI | `AccountingPage.tsx` reads accounts/periods; it does not expose the backend posting workflows | P0/P1 depending on production scope |
| HR & Payroll | Employee CRUD, contracts, leave, attendance, payroll run/finalization and payslip workflows | `HrPage.tsx` is a directory/payroll summary | P1 |
| POS | Register/shift/receipt transaction UI | `OptionalModulesPage.tsx` lists registers and counts only | P2 |
| Projects & Assets | Project/task/time entry and fixed-asset lifecycle screens | `OptionalModulesPage.tsx` lists records only | P2 |
| Approvals | Definition editor and approve/reject/delegate interaction UI | `WorkflowPage.tsx` reads inbox/notifications/exports only | P1 |
| Reports | Report catalog, parameterized execution, export/download UI and progress states | `WorkflowPage.tsx` only shows export counts | P2 |
| Notifications | Notification center, read/unread mutations and delivery status UI | `AppHeader.tsx` notification icon has no handler; `WorkflowPage.tsx` only counts | P2 |
| Localization | Functional language switching and translated resource catalog | `AppHeader.tsx` language button is decorative; profile has locale field | P2 |
| Mobile/PWA | Browser-verified mobile drawer, table behavior and overflow checks | No browser automation available in this audit | P1 |

## Acceptance criteria for closing these gaps

- Each module has a documented route-to-API map.
- Create/edit/detail actions use real APIs and show validation, loading, empty and error states.
- All list endpoints are bounded or paginated and preserve tenant/company/branch authorization.
- Browser smoke tests cover desktop, tablet and mobile widths.
- No visible control is rendered as interactive unless it has an implemented action or is explicitly disabled/labeled unavailable.
