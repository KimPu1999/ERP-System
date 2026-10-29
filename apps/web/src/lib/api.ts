const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1';
const API_ORIGIN = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000';
const CONFIGURED_TENANT_ID = import.meta.env.VITE_TENANT_ID;
const TENANT_STORAGE_KEY = 'erp_cloud.tenant_id';
const xsrfToken = () => document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=');

export const getActiveTenantId = (): string | undefined => {
  if (typeof window !== 'undefined') return window.localStorage.getItem(TENANT_STORAGE_KEY) ?? CONFIGURED_TENANT_ID;
  return CONFIGURED_TENANT_ID;
};

export const setActiveTenantId = (tenantId: string): void => {
  window.localStorage.setItem(TENANT_STORAGE_KEY, tenantId);
};

export const clearActiveTenantId = (): void => {
  window.localStorage.removeItem(TENANT_STORAGE_KEY);
};

export class ApiError extends Error {
  constructor(public readonly status: number, message: string) { super(message); }
}

export async function api<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response;
  const tenantId = getActiveTenantId();
  try { response = await fetch(`${API_URL}${path}`, { credentials: 'include', ...init, headers: { Accept: 'application/json', ...(xsrfToken() ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken()!) } : {}), ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}), ...init?.headers } }); }
  catch { throw new ApiError(0, `Cannot reach the ERP API at ${API_ORIGIN}. Start the Laravel backend and check the API URL.`); }
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new ApiError(response.status, body.message ?? `Request failed (${response.status}).`);
  return body as T;
}

export type HealthResponse = { status: 'ok' | 'degraded'; service: string; checks: { database: boolean; cache: boolean } };
export const getHealth = () => api<HealthResponse>('/health');
export type DashboardKpi = { value: string; previous_value: string | null; change_percent: string | null; available?: boolean; reason?: string | null };
export type DashboardOverview = {
  filters: { period: string; from: string; to: string; company_id?: string | null; branch_id?: string | null };
  currency: { code: string | null; decimal_places: number | null; configured: boolean };
  kpis: { revenue: DashboardKpi; sales_orders: DashboardKpi; pending_invoices: DashboardKpi; pending_deliveries: DashboardKpi; gross_profit: DashboardKpi; purchases: DashboardKpi; inventory_value: DashboardKpi; low_stock_products: DashboardKpi };
  charts: { revenue_expenses: Array<{ label: string; revenue: string; expenses: string }>; sales_trend: Array<{ label: string; value: string }>; sales_by_category: Array<{ label: string; value: string }> };
  recent_sales: Array<{ id: string; number: string; customer: string; date: string | null; amount: string; status: string }>;
  top_products: Array<{ product: string; sku: string; units_sold: string; revenue: string }>;
  top_products_by_volume: Array<{ product: string; sku: string; units_sold: string; revenue: string }>;
  inventory_alerts: Array<{ id: string; product: string; warehouse: string; available_stock: string; reorder_level: string; status: string }>;
  pending_approvals: Array<{ id: string; document_type: string; reference: string; requested_by: string; date: string; amount: string; status: string }>;
};
export const getDashboardOverview = (params: Record<string, string | undefined>) => { const entries = Object.entries(params).filter((entry): entry is [string, string] => Boolean(entry[1])); const query = new URLSearchParams(entries); return api<{ data: DashboardOverview }>(`/dashboard/overview?${query.toString()}`); };
export type DashboardAnalyticsParams = { period?: string; company_id?: string; branch_id?: string; date_from?: string; date_to?: string; year?: string; comparison_period?: 'previous_period' | 'previous_year' };
export type DashboardAnalyticsPoint = { key: string; label: string; current: string; previous: string };
export type DashboardAnalyticsBase = { available: boolean; reason: string | null; currency: { code: string | null; decimal_places: number | null }; accounting_basis: string | null; filters: { period: string; year: number | null; comparison_period: string; from: string; to: string; company_id: string | null; branch_id: string | null; timezone: string }; points: DashboardAnalyticsPoint[] };
export type DashboardRevenueTrend = DashboardAnalyticsBase & { granularity: 'day' | 'month' | null; current_period?: { from: string; to: string }; comparison_period?: { from: string; to: string }; totals: { current: string; previous: string } | null };
export type DashboardGrowth = Omit<DashboardAnalyticsBase, 'points'> & { current_period: { from: string; to: string; value: string }; previous_period: { from: string; to: string; value: string }; growth_percent: string | null; target: { value: string; percent: string } | null };
export type DashboardFinancialValue = { income: string; expenses: string; profit: string };
export type DashboardFinancialPerformance = Omit<DashboardAnalyticsBase, 'points'> & { granularity: 'day' | 'month' | null; totals: { current: DashboardFinancialValue; previous: DashboardFinancialValue } | null; points: Array<{ key: string; label: string; current: DashboardFinancialValue; previous: DashboardFinancialValue }> };
const dashboardAnalytics = <T>(path: string, params: DashboardAnalyticsParams) => { const entries = Object.entries(params).filter((entry): entry is [string, string] => Boolean(entry[1])); const query = new URLSearchParams(entries); return api<{ data: T }>(`${path}?${query.toString()}`); };
export const getDashboardRevenueTrend = (params: DashboardAnalyticsParams) => dashboardAnalytics<DashboardRevenueTrend>('/dashboard/revenue-trend', params);
export const getDashboardGrowth = (params: DashboardAnalyticsParams) => dashboardAnalytics<DashboardGrowth>('/dashboard/growth', params);
export const getDashboardFinancialPerformance = (params: DashboardAnalyticsParams) => dashboardAnalytics<DashboardFinancialPerformance>('/dashboard/financial-performance', params);
export type DashboardBusinessParams = DashboardAnalyticsParams & { limit?: string };
export type DashboardTransaction = { id: string; type: string; type_label: string; reference: string; party_name: string; occurred_at: string; amount: string; currency_code: string | null; direction: 'inflow' | 'outflow' | 'neutral'; status: string; detail_path: string | null; view_all_path: string | null };
export type DashboardRecentTransactions = { available: boolean; reason: string | null; filters: DashboardAnalyticsBase['filters']; items: DashboardTransaction[]; pagination: { limit: number; has_more: boolean } };
export type DashboardProductRankingItem = { rank: number; product_id: string; variant_id: string; product: string; sku: string; unit_name: string; quantity: string; revenue: string; currency_code: string | null; percentage: string | null; bar_percent: string; detail_path: string | null };
export type DashboardProductRanking = { available: boolean; reason: string | null; ranking: 'sales' | 'volume'; filters: DashboardAnalyticsBase['filters']; methodology: string | null; items: DashboardProductRankingItem[]; pagination: { limit: number; has_more: boolean } };
const dashboardBusiness = <T>(path: string, params: DashboardBusinessParams, extra: Record<string, string> = {}) => { const entries = Object.entries({ ...params, ...extra }).filter((entry): entry is [string, string] => Boolean(entry[1])); const query = new URLSearchParams(entries); return api<{ data: T }>(path + '?' + query.toString()); };
export const getDashboardRecentTransactions = (params: DashboardBusinessParams) => dashboardBusiness<DashboardRecentTransactions>('/dashboard/recent-transactions', params);
export const getDashboardTopProducts = (params: DashboardBusinessParams, ranking: 'sales' | 'volume') => dashboardBusiness<DashboardProductRanking>('/dashboard/top-products', params, { ranking });
export type Tenant = { id: string; name: string; slug: string; status?: string };
export type WorkspaceAccessWorkspace = Tenant & { tenant_status: string; membership_status: string; has_active_company: boolean };
export type WorkspaceInvitation = { id: string; tenant_id: string; tenant_name: string | null; company_id: string; company_name: string | null; expires_at: string };
export type WorkspaceAccess = { platform_admin: boolean; active_workspaces: Array<WorkspaceAccessWorkspace & { status: 'active' }>; restricted_workspaces: Array<WorkspaceAccessWorkspace & { status: string }>; pending_invitations: WorkspaceInvitation[]; can_create_workspace: boolean };
export type Company = { id: string; tenant_id: string; name: string; code: string; is_active: boolean };
export type Branch = { id: string; company_id: string; name: string; code: string; is_active: boolean };
export const getTenants = () => api<{ data: Tenant[] }>('/tenants');
export const getWorkspaceAccess = () => api<{ data: WorkspaceAccess }>('/workspace-access');
export const getPlatformTenants = () => api<{ data: { data: Tenant[]; current_page: number; last_page: number; total: number } }>('/platform/tenants');
export const getCompanies = () => api<{ data: { data: Company[] } }>('/companies');
export const getDashboardCompanies = () => api<{ data: { data: Company[] } }>('/dashboard/companies');
export const createTenant = (data: Pick<Tenant, 'name' | 'slug'>) => api<{ data: Tenant }>('/tenants', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
export const acceptInvitation = (invitationId: string, token: string) => api<{ status: string; tenant_id: string; company_id: string }>('/invitations/accept', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ invitation_id: invitationId, token }) });
export const createCompany = (data: Pick<Company, 'name' | 'code'>) => api<{ data: Company }>('/companies', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
export const getBranches = (companyId: string) => api<{ data: { data: Branch[] } }>(`/companies/${companyId}/branches`);
export const getDashboardBranches = (companyId: string) => api<{ data: { data: Branch[] } }>(`/dashboard/companies/${companyId}/branches`);
export const createBranch = (companyId: string, data: Pick<Branch, 'name' | 'code'>) => api<{ data: Branch }>(`/companies/${companyId}/branches`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
export async function csrf(): Promise<void> { try { const response = await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, { credentials: 'include' }); if (!response.ok) throw new Error(`HTTP ${response.status}`); } catch { throw new ApiError(0, `Cannot reach the ERP API at ${API_ORIGIN}. Start the Laravel backend before signing in.`); } }
export const login = async (data: { email: string; password: string; remember?: boolean }) => { await csrf(); return api<{ data?: User; mfa_required?: boolean }>('/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }); };
export const logout = async () => { await api('/auth/logout', { method: 'POST' }); };
export const register = async (data: { name: string; email: string; password: string; password_confirmation: string }) => { await csrf(); return api<{ data: User }>('/auth/register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }); };
export const requestPasswordReset = (email: string) => api<{ status: string }>('/auth/password/email', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) });
export type User = { id: string; name: string; email: string; status?: string; email_verified_at?: string | null; is_platform_super_admin?: boolean };
export const getRoles = () => api<{ data: Array<{ id: string; name: string; slug: string; permissions: Array<{ id: string; name: string }> }> }>('/roles');
export const getPermissions = () => api<{ data: Array<{ id: string; name: string; description?: string }> }>('/permissions');
export type Product = { id: string; name: string; status: string; variants: Array<{ id: string; sku: string; internal_barcode: string; price: string }> };
export type InventoryBalance = { id: string; variant_id: string; location_id: string; quantity: string; reserved_quantity: string; variant?: { sku: string }; location?: { name: string } };
export const getProducts = (search = '') => api<{ data: { data: Product[] } }>(`/products${search ? `?search=${encodeURIComponent(search)}` : ''}`);
export const getInventoryBalances = () => api<{ data: { data: InventoryBalance[] } }>('/inventory/balances');
export const getInventoryReport = () => api<{ data: { total_skus: number; balances: string; low_stock_alerts: unknown[] } }>('/inventory/report');
export type Supplier = { id: string; name: string; code: string; email?: string; status: string };
export type PurchaseOrder = { id: string; number: string; state: string; supplier?: Supplier; items: Array<{ variant_id: string; ordered_quantity: string; received_quantity: string; unit_price: string }> };
export const getSuppliers = () => api<{ data: { data: Supplier[] } }>('/suppliers');
export const getPurchaseOrders = () => api<{ data: { data: PurchaseOrder[] } }>('/purchase-orders');
export const getPurchaseRequests = () => api<{ data: { data: Array<{ id: string; number: string; state: string }> } }>('/purchase-requests');
export type Customer = { id: string; name: string; code: string; email?: string; phone?: string; status: string };
export type SalesDocument = { id: string; number: string; state: string; total?: string; customer?: Customer };
export const getCustomers = (search = '') => api<{ data: { data: Customer[] } }>(`/customers${search ? `?search=${encodeURIComponent(search)}` : ''}`);
export const createCustomer = (data: Pick<Customer, 'name' | 'code'>) => api<{ data: Customer }>('/customers', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
export const getSalesQuotations = () => api<{ data: { data: SalesDocument[] } }>('/sales/quotations');
export const getSalesOrders = () => api<{ data: { data: SalesDocument[] } }>('/sales/orders');
export const getSalesInvoices = () => api<{ data: { data: SalesDocument[] } }>('/sales/invoices');
export type LedgerAccount = { id: string; code: string; name: string; type: string; normal_balance: string; is_active: boolean };
export const getLedgerAccounts = () => api<{ data: { data: LedgerAccount[] } }>('/accounting/accounts');
export const getFiscalPeriods = () => api<{ data: { data: Array<{ id: string; name: string; state: string; starts_on: string; ends_on: string }> } }>('/accounting/periods');
export const getTrialBalance = (companyId: string, from: string, to: string) => api<{ data: Array<{ account: string; debit: string; credit: string; balance: string }> }>(`/accounting/reports/trial-balance?company_id=${encodeURIComponent(companyId)}&from=${from}&to=${to}`);
export type Employee = { id: string; employee_number: string; legal_name: string; preferred_name?: string; status: string; company_id: string; joined_on: string };
export type PayrollRun = { id: string; name: string; state: string; period_starts_on: string; period_ends_on: string };
export const getEmployees = (search = '') => api<{ data: { data: Employee[] } }>(`/hr/employees${search ? `?search=${encodeURIComponent(search)}` : ''}`);
export const getPayrollRuns = () => api<{ data: { data: PayrollRun[] } }>('/hr/payroll-runs');
export type ModuleFlag = { id: string; module_key: 'pos' | 'projects' | 'fixed_assets'; enabled: boolean };
export const getModules = () => api<{ data: ModuleFlag[] }>('/modules');
export const getProjects = () => api<{ data: { data: Array<{ id: string; code: string; name: string; state: string; budget_amount: string }> } }>('/projects');
export const getPosRegisters = () => api<{ data: { data: Array<{ id: string; code: string; name: string; status: string }> } }>('/pos/registers');
export const getAssets = () => api<{ data: { data: Array<{ id: string; asset_number: string; name: string; state: string; acquisition_cost: string }> } }>('/fixed-assets');
export const getApprovalInbox = () => api<{ data: Array<{ id: string; document_type: string; amount: string; current_level: number; state: string }> }>('/approvals/inbox');
export const getNotifications = () => api<{ data: { data: Array<{ id: string; title: string; body: string; read_at?: string | null }> } }>('/notifications');
export const getReportExports = () => api<{ data: { data: Array<{ id: string; report_key: string; state: string; format: string }> } }>('/reports/exports');
