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
import { InventoryPage } from './pages/InventoryPage';
import { SuppliersPage } from './pages/SuppliersPage';
import { PurchasingPage } from './pages/PurchasingPage';
import { CrmSalesPage } from './pages/CrmSalesPage';
import { AccountingPage } from './pages/AccountingPage';
import { HrPage } from './pages/HrPage';
import { OptionalModulesPage } from './pages/OptionalModulesPage';
import { WorkflowPage } from './pages/WorkflowPage';
import { PlatformControlPage } from './pages/PlatformControlPage';
import { CompanyDashboard } from './pages/CompanyDashboard';
import { CompanyProfilePage } from './pages/CompanyProfilePage';
import { DepartmentManagementPage } from './pages/DepartmentManagementPage';
import { OrganizationMembersPage } from './pages/OrganizationMembersPage';
import { AcceptInvitationPage } from './pages/AcceptInvitationPage';
import { MemberDetailsPage } from './pages/MemberDetailsPage';
import { OrganizationLayout } from './layouts/OrganizationLayout';

const client = new QueryClient();

function HealthPage() {
  const health = useQuery({ queryKey: ['health'], queryFn: getHealth });
  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">Platform monitoring</p><h1>System health</h1><p className="muted">Operational diagnostics for authorized platform administrators.</p></div><span className="date-chip">Live status</span></div><div className="metric-grid"><div className="metric"><span>API service</span><strong className={health.data?.status === 'ok' ? 'text-success' : ''}>{health.data?.status ?? (health.isError ? 'Unavailable' : 'Checking')}</strong></div><div className="metric"><span>Database</span><strong>{health.data ? (health.data.checks.database ? 'Connected' : 'Unavailable') : '—'}</strong></div><div className="metric"><span>Cache</span><strong>{health.data ? (health.data.checks.cache ? 'Connected' : 'Unavailable') : '—'}</strong></div></div>{health.isError && <div className="alert alert-danger">{health.error.message}</div>}</section>;
}

export default function App() {
  return <QueryClientProvider client={client}><BrowserRouter><AuthProvider><Routes>
    <Route element={<AuthLayout />}>
      <Route path="/login" element={<LoginPage />} /><Route path="/register" element={<RegisterPage />} /><Route path="/verify-email" element={<VerifyEmailPage />} /><Route path="/reset-password" element={<ResetPasswordPage />} /><Route path="/verify-mfa" element={<MfaVerifyPage />} /><Route path="/accept-invitation" element={<AcceptInvitationPage />} />
    </Route>
    <Route element={<PlatformRoute />}><Route element={<PlatformLayout />}><Route path="/platform" element={<PlatformDashboard />} /><Route path="/platform/tenants" element={<TenantManagement />} /><Route path="/platform/monitoring" element={<HealthPage />} /><Route path="/platform/control" element={<PlatformControlPage />} /></Route></Route>
    <Route element={<AuthenticatedRoute />}><Route element={<CompanyLayout />}>
      <Route path="/" element={<CompanyDashboard />} />
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
      <Route path="/company" element={<CompanySetup />} /><Route path="/branches" element={<BranchManagement />} /><Route path="/catalog" element={<ProductCatalogPage />} /><Route path="/inventory" element={<InventoryPage />} /><Route path="/suppliers" element={<SuppliersPage />} /><Route path="/purchasing" element={<PurchasingPage />} /><Route path="/crm-sales" element={<CrmSalesPage />} /><Route path="/accounting" element={<AccountingPage />} /><Route path="/hr" element={<HrPage />} /><Route path="/optional-modules" element={<OptionalModulesPage />} /><Route path="/workflow" element={<WorkflowPage />} /><Route path="/roles" element={<Navigate to="/organization/roles" replace />} />
    </Route></Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></AuthProvider></BrowserRouter></QueryClientProvider>;
}
