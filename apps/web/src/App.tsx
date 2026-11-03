import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './lib/auth';
import { AuthenticatedRoute, BusinessRoute, PlatformRoute } from './components/ProtectedRoute';
import { AuthLayout } from './layouts/AuthLayout';
import { CompanyLayout } from './layouts/CompanyLayout';
import { PlatformLayout } from './layouts/PlatformLayout';
import { PlatformDashboard } from './pages/PlatformDashboard';
import { TenantManagement } from './pages/TenantManagement';
import { PlatformAdministratorsPage } from './pages/PlatformAdministratorsPage';
import { PlatformBusinessAdminsPage } from './pages/PlatformBusinessAdminsPage';
import { PlatformBusinessDetailPage } from './pages/PlatformBusinessDetailPage';
import { CompanySetup } from './pages/CompanySetup';
import { BranchManagement } from './pages/BranchManagement';
import { BranchDetailsPage } from './pages/BranchDetailsPage';
import { LoginPage } from './pages/LoginPage';
import { PlatformLoginPage } from './pages/PlatformLoginPage';
import { BusinessLoginPage } from './pages/BusinessLoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { VerifyEmailPage } from './pages/VerifyEmailPage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';
import { MfaVerifyPage } from './pages/MfaVerifyPage';
import { AccessDeniedPage } from './pages/AccessDeniedPage';
import { RoleManagementPage } from './pages/RoleManagementPage';
import { OrganizationPage } from './pages/OrganizationPage';
import { ProductCatalogPage } from './pages/ProductCatalogPage';
import { ProductCategoriesPage } from './pages/ProductCategoriesPage';
import { CatalogReferencesPage } from './pages/CatalogReferencesPage';
import { InventoryPage } from './pages/InventoryPage';
import { WarehouseManagementPage } from './pages/WarehouseManagementPage';
import { InventoryOperationsPage } from './pages/InventoryOperationsPage';
import { SuppliersPage } from './pages/SuppliersPage';
import { SupplierOnboardingPage } from './pages/SupplierOnboardingPage';
import { SupplierFinancialPage } from './pages/SupplierFinancialPage';
import { SupplierCatalogPage } from './pages/SupplierCatalogPage';
import { SupplierPerformancePage } from './pages/SupplierPerformancePage';
import { SupplierPortalDashboardPage } from './pages/SupplierPortalDashboardPage';
import { SupplierPortalPurchaseOrdersPage } from './pages/SupplierPortalPurchaseOrdersPage';
import { SupplierPortalInvitationPage } from './pages/SupplierPortalInvitationPage';
import { SupplierPortalLayout } from './layouts/SupplierPortalLayout';
import { PurchaseOrderDetailsPage } from './pages/PurchaseOrderDetailsPage';
import { PurchaseOrdersPage } from './pages/PurchaseOrdersPage';
import { PurchaseApprovalsPage } from './pages/PurchaseApprovalsPage';
import { PurchaseRequestsPage } from './pages/PurchaseRequestsPage';
import { PurchaseOrderFormPage } from './pages/PurchaseOrderFormPage';
import { GoodsReceiptsPage } from './pages/GoodsReceiptsPage';
import { GoodsReceiptFormPage } from './pages/GoodsReceiptFormPage';
import { GoodsReceiptDetailsPage } from './pages/GoodsReceiptDetailsPage';
import { PurchasingLayout } from './layouts/PurchasingLayout';
import { CrmSalesPage } from './pages/CrmSalesPage';
import { SalesReportsPage } from './pages/SalesReportsPage';
import { SalesSettingsPage } from './pages/SalesSettingsPage';
import { SalesQuotationDetailsPage } from './pages/SalesQuotationDetailsPage';
import { AccountingPage } from './pages/AccountingPage';
import { JournalEntriesPage } from './pages/JournalEntriesPage';
import { FinancialReportsPage } from './pages/FinancialReportsPage';
import { AccountingIntegrationPage } from './pages/AccountingIntegrationPage';
import { HrPage } from './pages/HrPage';
import { OptionalModulesPage } from './pages/OptionalModulesPage';
import { PosWorkspacePage } from './pages/PosWorkspacePage';
import { ProjectWorkspacePage } from './pages/ProjectWorkspacePage';
import { FixedAssetsWorkspacePage } from './pages/FixedAssetsWorkspacePage';
import { WorkflowPage } from './pages/WorkflowPage';
import { PlatformControlPage } from './pages/PlatformControlPage';
import { PlatformSubscriptionPlansPage } from './pages/PlatformSubscriptionPlansPage';
import { PlatformBillingDocumentsPage } from './pages/PlatformBillingDocumentsPage';
import { PlatformAuditLogsPage } from './pages/PlatformAuditLogsPage';
import { PlatformSecurityPage } from './pages/PlatformSecurityPage';
import { PlatformSecuritySectionPage } from './pages/PlatformSecuritySectionPage';
import { PlatformMfaEnrollmentPage } from './pages/PlatformMfaEnrollmentPage';
import { PlatformUsersPage } from './pages/PlatformUsersPage';
import { PlatformSettingsPage } from './pages/PlatformSettingsPage';
import { PlatformAnalyticsCenterPage } from './pages/PlatformAnalyticsCenterPage';
import { PlatformMonitoringCenterPage } from './pages/PlatformMonitoringCenterPage';
import { CompanyDashboard } from './pages/CompanyDashboard';
import { CompanyProfilePage } from './pages/CompanyProfilePage';
import { DepartmentManagementPage } from './pages/DepartmentManagementPage';
import { OrganizationMembersPage } from './pages/OrganizationMembersPage';
import { AcceptInvitationPage } from './pages/AcceptInvitationPage';
import { PlatformAcceptInvitePage } from './pages/PlatformAcceptInvitePage';
import { PlatformAdminInvitationsPage } from './pages/PlatformAdminInvitationsPage';
import { MemberDetailsPage } from './pages/MemberDetailsPage';
import { OrganizationLayout } from './layouts/OrganizationLayout';
import { SalesWorkspaceLayout } from './layouts/SalesWorkspaceLayout';
import { SupplierBillsPage } from './pages/SupplierBillsPage';
import { SupplierBillFormPage } from './pages/SupplierBillFormPage';
import { SupplierBillDetailsPage } from './pages/SupplierBillDetailsPage';
import { AccountsPayablePage } from './pages/AccountsPayablePage';
import { SupplierPaymentsPage } from './pages/SupplierPaymentsPage';
import { SupplierCreditsPage } from './pages/SupplierCreditsPage';
import { PurchaseReturnsPage } from './pages/PurchaseReturnsPage';
import { PurchaseReturnDetailsPage } from './pages/PurchaseReturnDetailsPage';
import { PurchasingSettingsPage } from './pages/PurchasingSettingsPage';
import { CrmOverviewPage } from './pages/CrmPhase3Pages';
import { SalesOrdersPage } from './pages/SalesOrdersPage';
import { SalesOrderDetailsPage } from './pages/SalesOrderDetailsPage';
import { SalesFulfillmentPage } from './pages/SalesFulfillmentPage';
import { SalesFulfillmentDetailsPage } from './pages/SalesFulfillmentDetailsPage';
import { SalesInvoiceDetailsPage, SalesInvoicesPage } from './pages/SalesInvoicesPage';
import { SalesPaymentsPage } from './pages/SalesPaymentsPage';
import { SalesReturnDetailsPage, SalesReturnsPage } from './pages/SalesReturnsPage';
import { SupplierIntelligencePage } from './pages/SupplierIntelligencePage';
import { ModernCustomerDirectoryPage } from './pages/CustomerDirectoryPage';
import { ModernLeadDirectoryPage, ModernOpportunityPage } from './pages/CrmLeadsOpportunitiesPage';
import { ModernActivitiesPage, ModernPipelinePage, ModernQuotationsPage } from './pages/CrmOperationsPages';
import { I18nProvider } from './lib/i18n';

const client = new QueryClient();

export default function App() {
  return <QueryClientProvider client={client}><BrowserRouter><I18nProvider><AuthProvider><Routes>
    <Route element={<AuthLayout />}>
      <Route path="/platform/login" element={<PlatformLoginPage />} />
      <Route path="/platform/accept-invite" element={<PlatformAcceptInvitePage />} />
      <Route path="/platform/forgot-password" element={<ResetPasswordPage />} />
      <Route path="/platform/reset-password" element={<ResetPasswordPage />} />
      <Route path="/business/login" element={<BusinessLoginPage />} />
      <Route path="/login" element={<LoginPage />} /><Route path="/register" element={<RegisterPage />} /><Route path="/verify-email" element={<VerifyEmailPage />} /><Route path="/reset-password" element={<ResetPasswordPage />} /><Route path="/verify-mfa" element={<MfaVerifyPage />} /><Route path="/accept-invitation" element={<AcceptInvitationPage />} /><Route path="/access-denied" element={<AccessDeniedPage />} />
    </Route>
    <Route path="/platform/mfa-enroll" element={<PlatformMfaEnrollmentPage mode="platform" />} /><Route path="/mfa-enroll" element={<PlatformMfaEnrollmentPage mode="business" />} />
    <Route element={<PlatformRoute />}><Route element={<PlatformLayout />}><Route path="/platform" element={<PlatformDashboard />} /><Route path="/platform/dashboard" element={<PlatformDashboard />} /><Route path="/platform/tenants" element={<TenantManagement />} /><Route path="/platform/businesses" element={<TenantManagement />} /><Route path="/platform/tenants/:tenantId" element={<PlatformBusinessDetailPage />} /><Route path="/platform/administrators" element={<PlatformAdministratorsPage />} /><Route path="/platform/business-admins" element={<PlatformBusinessAdminsPage />} /><Route path="/platform/users" element={<PlatformUsersPage />} /><Route path="/platform/invitations" element={<PlatformAdminInvitationsPage />} /><Route path="/platform/analytics" element={<PlatformAnalyticsCenterPage mode="overview" />} /><Route path="/platform/analytics/businesses" element={<PlatformAnalyticsCenterPage mode="businesses" />} /><Route path="/platform/analytics/users" element={<PlatformAnalyticsCenterPage mode="users" />} /><Route path="/platform/analytics/subscriptions" element={<PlatformAnalyticsCenterPage mode="subscriptions" />} /><Route path="/platform/analytics/revenue" element={<PlatformAnalyticsCenterPage mode="revenue" />} /><Route path="/platform/analytics/usage" element={<PlatformAnalyticsCenterPage mode="usage" />} /><Route path="/platform/reports/financial" element={<PlatformAnalyticsCenterPage mode="financial" />} /><Route path="/platform/reports/exports" element={<PlatformAnalyticsCenterPage mode="exports" />} /><Route path="/platform/monitoring" element={<PlatformMonitoringCenterPage mode="overview" />} /><Route path="/platform/monitoring/api" element={<PlatformMonitoringCenterPage mode="api" />} /><Route path="/platform/monitoring/database" element={<PlatformMonitoringCenterPage mode="database" />} /><Route path="/platform/monitoring/queues" element={<PlatformMonitoringCenterPage mode="queues" />} /><Route path="/platform/monitoring/email" element={<PlatformMonitoringCenterPage mode="email" />} /><Route path="/platform/monitoring/scheduler" element={<PlatformMonitoringCenterPage mode="scheduler" />} /><Route path="/platform/monitoring/servers" element={<PlatformMonitoringCenterPage mode="servers" />} /><Route path="/platform/monitoring/errors" element={<PlatformMonitoringCenterPage mode="errors" />} /><Route path="/platform/monitoring/alerts" element={<PlatformMonitoringCenterPage mode="alerts" />} /><Route path="/platform/monitoring/incidents" element={<PlatformMonitoringCenterPage mode="incidents" />} /><Route path="/platform/monitoring/settings" element={<PlatformMonitoringCenterPage mode="settings" />} /><Route path="/platform/audit-logs" element={<PlatformAuditLogsPage />} /><Route path="/platform/security" element={<PlatformSecurityPage />} /><Route path="/platform/security/login-activity" element={<PlatformSecuritySectionPage mode="login-activity" />} /><Route path="/platform/security/sessions" element={<PlatformSecuritySectionPage mode="sessions" />} /><Route path="/platform/security/mfa" element={<PlatformSecuritySectionPage mode="mfa" />} /><Route path="/platform/security/permissions" element={<PlatformSecuritySectionPage mode="permissions" />} /><Route path="/platform/security/alerts" element={<PlatformSecuritySectionPage mode="alerts" />} /><Route path="/platform/security/access-history" element={<PlatformSecuritySectionPage mode="access-history" />} /><Route path="/platform/security/reports" element={<PlatformAnalyticsCenterPage mode="security-exports" />} /><Route path="/platform/security/settings" element={<PlatformSecuritySectionPage mode="settings" />} /><Route path="/platform/security/audit-logs" element={<PlatformAuditLogsPage />} /><Route path="/platform/settings" element={<PlatformSettingsPage />} /><Route path="/platform/settings/:section" element={<PlatformSettingsPage />} /><Route path="/platform/control" element={<PlatformControlPage />} /><Route path="/platform/subscription-plans" element={<PlatformSubscriptionPlansPage />} /><Route path="/platform/subscriptions" element={<PlatformControlPage />} /><Route path="/platform/invoices" element={<PlatformBillingDocumentsPage />} /><Route path="/platform/payments" element={<PlatformBillingDocumentsPage />} /></Route></Route>
    <Route element={<BusinessRoute />}><Route element={<CompanyLayout />}><Route path="/business/dashboard" element={<CompanyDashboard />} /><Route path="/app/dashboard" element={<CompanyDashboard />} /></Route></Route>
    <Route element={<AuthenticatedRoute />}><Route element={<CompanyLayout />}>
      <Route path="/" element={<CompanyDashboard />} />
      <Route path="/catalog/references" element={<CatalogReferencesPage />} />
      <Route path="/inventory/warehouses" element={<WarehouseManagementPage />} />
      <Route path="/inventory/operations" element={<InventoryOperationsPage />} />
      <Route path="/organization" element={<OrganizationLayout />}>
        <Route index element={<OrganizationPage />} />
        <Route path="profile" element={<CompanyProfilePage />} />
        <Route path="branches" element={<BranchManagement />} />
        <Route path="branches/:branchId" element={<BranchDetailsPage />} />
        <Route path="departments" element={<DepartmentManagementPage />} />
        <Route path="structure" element={<OrganizationPage />} />
        <Route path="members" element={<OrganizationMembersPage />} />
        <Route path="members/:memberId" element={<MemberDetailsPage />} />
        <Route path="roles" element={<RoleManagementPage />} />
      </Route>
      <Route path="/company" element={<CompanySetup />} /><Route path="/branches" element={<BranchManagement />} /><Route path="/catalog" element={<ProductCatalogPage />} /><Route path="/catalog/categories" element={<ProductCategoriesPage />} /><Route path="/inventory" element={<InventoryPage />} /><Route path="/suppliers" element={<SuppliersPage />} /><Route path="/purchasing" element={<PurchasingLayout />}><Route index element={<PurchaseOrdersPage />} /><Route path="suppliers" element={<SuppliersPage />} /><Route path="approvals" element={<PurchaseApprovalsPage />} /><Route path="goods-receipts" element={<GoodsReceiptsPage />} /><Route path="goods-receipts/new" element={<GoodsReceiptFormPage />} /><Route path="goods-receipts/:goodsReceiptId/edit" element={<GoodsReceiptFormPage edit />} /><Route path="goods-receipts/:goodsReceiptId" element={<GoodsReceiptDetailsPage />} /><Route path="supplier-bills" element={<SupplierBillsPage />} /><Route path="supplier-bills/new" element={<SupplierBillFormPage />} /><Route path="supplier-bills/:supplierBillId/edit" element={<SupplierBillFormPage edit />} /><Route path="supplier-bills/:supplierBillId" element={<SupplierBillDetailsPage />} /><Route path="accounts-payable" element={<AccountsPayablePage />} /><Route path="payments" element={<SupplierPaymentsPage />} /><Route path="new" element={<PurchaseOrderFormPage />} /><Route path=":purchaseOrderId/edit" element={<PurchaseOrderFormPage edit />} /><Route path=":purchaseOrderId" element={<PurchaseOrderDetailsPage />} /></Route>
      <Route path="/purchasing/returns" element={<PurchasingLayout />}><Route index element={<PurchaseReturnsPage />} /></Route>
      <Route path="/purchasing/requests" element={<PurchasingLayout />}><Route index element={<PurchaseRequestsPage />} /></Route>
      <Route path="/purchasing/returns/:returnId" element={<PurchasingLayout />}><Route index element={<PurchaseReturnDetailsPage />} /></Route>
      <Route path="/purchasing/suppliers/onboarding" element={<PurchasingLayout />}><Route index element={<SupplierOnboardingPage />} /></Route>
      <Route path="/purchasing/suppliers/financial" element={<PurchasingLayout />}><Route index element={<SupplierFinancialPage />} /></Route>
      <Route path="/purchasing/suppliers/catalog" element={<PurchasingLayout />}><Route index element={<SupplierCatalogPage />} /></Route>
      <Route path="/purchasing/suppliers/performance" element={<PurchasingLayout />}><Route index element={<SupplierPerformancePage />} /></Route>
      <Route path="/purchasing/suppliers/analytics" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="analytics" />} /></Route>
      <Route path="/purchasing/suppliers/reports" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="reports" />} /></Route>
      <Route path="/purchasing/suppliers/automations" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="automations" />} /></Route>
      <Route path="/purchasing/suppliers/notifications" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="notifications" />} /></Route>
      <Route path="/purchasing/suppliers/monitoring" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="monitoring" />} /></Route>
      <Route path="/purchasing/suppliers/portal-activity" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="portal" />} /></Route>
      <Route path="/purchasing/suppliers/access-reviews" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="reviews" />} /></Route>
      <Route path="/supplier-portal" element={<SupplierPortalLayout />}><Route path="dashboard" element={<SupplierPortalDashboardPage />} /><Route path="purchase-orders" element={<SupplierPortalPurchaseOrdersPage />} /></Route>
      <Route path="/supplier-portal/accept-invitation" element={<SupplierPortalInvitationPage />} />
      <Route path="/purchasing/settings" element={<PurchasingLayout />}><Route index element={<PurchasingSettingsPage />} /></Route>
      <Route path="/purchasing/supplier-credits" element={<PurchasingLayout />}><Route index element={<SupplierCreditsPage />} /></Route>
      <Route path="/crm-sales/reports" element={<SalesWorkspaceLayout />}><Route index element={<SalesReportsPage />} /></Route>
      <Route path="/crm-sales/settings" element={<SalesWorkspaceLayout />}><Route index element={<SalesSettingsPage />} /></Route>
      <Route path="/crm-sales" element={<SalesWorkspaceLayout />}><Route index element={<CrmSalesPage />} /><Route path="fulfillment" element={<SalesFulfillmentPage />} /><Route path="fulfillment/:fulfillmentId" element={<SalesFulfillmentDetailsPage />} /><Route path="orders" element={<SalesOrdersPage />} /><Route path="orders/:orderId" element={<SalesOrderDetailsPage />} /><Route path="quotations" element={<ModernQuotationsPage />} /><Route path="quotations/:quotationId" element={<SalesQuotationDetailsPage />} /><Route path="invoices" element={<SalesInvoicesPage />} /><Route path="invoices/:invoiceId" element={<SalesInvoiceDetailsPage />} /><Route path="payments" element={<SalesPaymentsPage />} /><Route path="returns" element={<SalesReturnsPage />} /><Route path="returns/:returnId" element={<SalesReturnDetailsPage />} /><Route path="customers" element={<ModernCustomerDirectoryPage />} /><Route path="customers/:customerId" element={<ModernCustomerDirectoryPage />} /><Route path="leads" element={<ModernLeadDirectoryPage />} /><Route path="opportunities" element={<ModernOpportunityPage />} /><Route path="pipeline" element={<ModernPipelinePage />} /><Route path="activities" element={<ModernActivitiesPage />} /><Route path="crm-overview" element={<CrmOverviewPage />} /></Route>
      <Route path="/accounting" element={<AccountingPage />} /><Route path="/accounting/journals" element={<JournalEntriesPage />} /><Route path="/accounting/reports" element={<FinancialReportsPage />} /><Route path="/accounting/integration" element={<AccountingIntegrationPage />} /><Route path="/hr" element={<HrPage />} /><Route path="/optional-modules" element={<OptionalModulesPage />} /><Route path="/optional-modules/pos" element={<PosWorkspacePage />} /><Route path="/optional-modules/projects" element={<ProjectWorkspacePage />} /><Route path="/optional-modules/assets" element={<FixedAssetsWorkspacePage />} /><Route path="/workflow" element={<WorkflowPage />} /><Route path="/roles" element={<Navigate to="/organization/roles" replace />} />
    </Route></Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></AuthProvider></I18nProvider></BrowserRouter></QueryClientProvider>;
}
