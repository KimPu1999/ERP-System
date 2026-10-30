import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './lib/auth';
import { AuthenticatedRoute, PlatformRoute } from './components/ProtectedRoute';
import { AuthLayout } from './layouts/AuthLayout';
import { CompanyLayout } from './layouts/CompanyLayout';
import { PlatformLayout } from './layouts/PlatformLayout';
import { getHealth } from './lib/api';
import { PlatformDashboard } from './pages/PlatformDashboard';
import { TenantManagement } from './pages/TenantManagement';
import { CompanySetup } from './pages/CompanySetup';
import { BranchManagement } from './pages/BranchManagement';
import { BranchDetailsPage } from './pages/BranchDetailsPage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { VerifyEmailPage } from './pages/VerifyEmailPage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';
import { MfaVerifyPage } from './pages/MfaVerifyPage';
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
import { PurchaseOrderFormPage } from './pages/PurchaseOrderFormPage';
import { GoodsReceiptsPage } from './pages/GoodsReceiptsPage';
import { GoodsReceiptFormPage } from './pages/GoodsReceiptFormPage';
import { GoodsReceiptDetailsPage } from './pages/GoodsReceiptDetailsPage';
import { PurchasingLayout } from './layouts/PurchasingLayout';
import { CrmSalesPage } from './pages/CrmSalesPage';
import { SalesQuotationsPage } from './pages/SalesQuotationsPage';
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
import { CompanyDashboard } from './pages/CompanyDashboard';
import { CompanyProfilePage } from './pages/CompanyProfilePage';
import { DepartmentManagementPage } from './pages/DepartmentManagementPage';
import { OrganizationMembersPage } from './pages/OrganizationMembersPage';
import { AcceptInvitationPage } from './pages/AcceptInvitationPage';
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
import { CrmOverviewPage, CustomerDirectoryPage, LeadDirectoryPage, OpportunityDirectoryPage, PipelinePage, ActivitiesPage } from './pages/CrmPhase3Pages';
import { SalesOrdersPage } from './pages/SalesOrdersPage';
import { SalesOrderDetailsPage } from './pages/SalesOrderDetailsPage';
import { SalesFulfillmentPage } from './pages/SalesFulfillmentPage';
import { SalesFulfillmentDetailsPage } from './pages/SalesFulfillmentDetailsPage';
import { SupplierIntelligencePage } from './pages/SupplierIntelligencePage';
import { I18nProvider, useI18n } from './lib/i18n';

const client = new QueryClient();

function HealthPage() {
  const { t } = useI18n();
  const health = useQuery({ queryKey: ['health'], queryFn: getHealth });
  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">{t('nav.monitoring')}</p><h1>System health</h1></div><span className="date-chip">{t('dashboard.live')}</span></div><div className="metric-grid"><div className="metric"><span>API service</span><strong className={health.data?.status === 'ok' ? 'text-success' : ''}>{health.data?.status ?? (health.isError ? 'Unavailable' : 'Checking')}</strong></div><div className="metric"><span>Database</span><strong>{health.data ? (health.data.checks.database ? 'Connected' : 'Unavailable') : '—'}</strong></div><div className="metric"><span>Cache</span><strong>{health.data ? (health.data.checks.cache ? 'Connected' : 'Unavailable') : '—'}</strong></div></div>{health.isError && <div className="alert alert-danger">{health.error.message}</div>}</section>;
}

export default function App() {
  return <QueryClientProvider client={client}><BrowserRouter><I18nProvider><AuthProvider><Routes>
    <Route element={<AuthLayout />}>
      <Route path="/login" element={<LoginPage />} /><Route path="/register" element={<RegisterPage />} /><Route path="/verify-email" element={<VerifyEmailPage />} /><Route path="/reset-password" element={<ResetPasswordPage />} /><Route path="/verify-mfa" element={<MfaVerifyPage />} /><Route path="/accept-invitation" element={<AcceptInvitationPage />} />
    </Route>
    <Route element={<PlatformRoute />}><Route element={<PlatformLayout />}><Route path="/platform" element={<PlatformDashboard />} /><Route path="/platform/tenants" element={<TenantManagement />} /><Route path="/platform/monitoring" element={<HealthPage />} /><Route path="/platform/control" element={<PlatformControlPage />} /></Route></Route>
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
      <Route path="/purchasing/returns/:returnId" element={<PurchasingLayout />}><Route index element={<PurchaseReturnDetailsPage />} /></Route>
      <Route path="/purchasing/suppliers/onboarding" element={<PurchasingLayout />}><Route index element={<SupplierOnboardingPage />} /></Route>
      <Route path="/purchasing/suppliers/financial" element={<PurchasingLayout />}><Route index element={<SupplierFinancialPage />} /></Route>
      <Route path="/purchasing/suppliers/catalog" element={<PurchasingLayout />}><Route index element={<SupplierCatalogPage />} /></Route>
      <Route path="/purchasing/suppliers/performance" element={<PurchasingLayout />}><Route index element={<SupplierPerformancePage />} /></Route>
      <Route path="/purchasing/suppliers/analytics" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="analytics" />} /></Route>
      <Route path="/purchasing/suppliers/reports" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="reports" />} /></Route>
      <Route path="/purchasing/suppliers/automations" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="automations" />} /></Route>
      <Route path="/purchasing/suppliers/notifications" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="notifications" />} /></Route>
      <Route path="/purchasing/suppliers/portal-activity" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="portal" />} /></Route>
      <Route path="/purchasing/suppliers/access-reviews" element={<PurchasingLayout />}><Route index element={<SupplierIntelligencePage mode="reviews" />} /></Route>
      <Route path="/supplier-portal" element={<SupplierPortalLayout />}><Route path="dashboard" element={<SupplierPortalDashboardPage />} /><Route path="purchase-orders" element={<SupplierPortalPurchaseOrdersPage />} /></Route>
      <Route path="/supplier-portal/accept-invitation" element={<SupplierPortalInvitationPage />} />
      <Route path="/purchasing/settings" element={<PurchasingLayout />}><Route index element={<PurchasingSettingsPage />} /></Route>
      <Route path="/purchasing/supplier-credits" element={<PurchasingLayout />}><Route index element={<SupplierCreditsPage />} /></Route>
      <Route path="/crm-sales" element={<SalesWorkspaceLayout />}><Route index element={<CrmSalesPage />} /><Route path="fulfillment" element={<SalesFulfillmentPage />} /><Route path="fulfillment/:fulfillmentId" element={<SalesFulfillmentDetailsPage />} /><Route path="orders" element={<SalesOrdersPage />} /><Route path="orders/:orderId" element={<SalesOrderDetailsPage />} /><Route path="quotations" element={<SalesQuotationsPage />} /><Route path="quotations/:quotationId" element={<SalesQuotationDetailsPage />} /><Route path="customers" element={<CustomerDirectoryPage />} /><Route path="customers/:customerId" element={<CustomerDirectoryPage />} /><Route path="leads" element={<LeadDirectoryPage />} /><Route path="opportunities" element={<OpportunityDirectoryPage />} /><Route path="pipeline" element={<PipelinePage />} /><Route path="activities" element={<ActivitiesPage />} /><Route path="crm-overview" element={<CrmOverviewPage />} /></Route>
      <Route path="/accounting" element={<AccountingPage />} /><Route path="/accounting/journals" element={<JournalEntriesPage />} /><Route path="/accounting/reports" element={<FinancialReportsPage />} /><Route path="/accounting/integration" element={<AccountingIntegrationPage />} /><Route path="/hr" element={<HrPage />} /><Route path="/optional-modules" element={<OptionalModulesPage />} /><Route path="/optional-modules/pos" element={<PosWorkspacePage />} /><Route path="/optional-modules/projects" element={<ProjectWorkspacePage />} /><Route path="/optional-modules/assets" element={<FixedAssetsWorkspacePage />} /><Route path="/workflow" element={<WorkflowPage />} /><Route path="/roles" element={<Navigate to="/organization/roles" replace />} />
    </Route></Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></AuthProvider></I18nProvider></BrowserRouter></QueryClientProvider>;
}
