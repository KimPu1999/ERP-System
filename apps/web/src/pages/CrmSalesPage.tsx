import { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Building2, ChevronRight, CreditCard, FileText, RefreshCw, Receipt, ShoppingCart, Target, TrendingUp, Truck, Users, WalletCards, type LucideIcon } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { ApiError, getDashboardBranches, getDashboardCompanies, getDashboardOverview, getMe, type DashboardOverview } from '../lib/api';

type SalesPeriod = 'today' | 'last_7_days' | 'last_30_days' | 'this_month' | 'this_year' | 'custom';
const periods: Array<[SalesPeriod, string]> = [['today', 'Today'], ['last_7_days', 'Last 7 days'], ['last_30_days', 'Last 30 days'], ['this_month', 'This month'], ['this_year', 'This year'], ['custom', 'Custom range']];
const readPeriod = (value: string | null): SalesPeriod => periods.some(([key]) => key === value) ? value as SalesPeriod : 'last_30_days';

const money = (value: string | null | undefined, currency: string | null, configured = true) => {
  if (!configured || value === null || value === undefined) return '—';
  const amount = Number(value);
  return Number.isFinite(amount) ? new Intl.NumberFormat(undefined, { style: 'currency', currency: currency ?? 'USD', maximumFractionDigits: 2 }).format(amount) : '—';
};
const number = (value: string | number | null | undefined) => value === null || value === undefined ? '—' : new Intl.NumberFormat().format(Number(value));
const date = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`)) : '—';

function SalesKpiCard({ label, value, comparison, description, icon: Icon, unavailable = false }: { label: string; value: string; comparison?: string | null; description: string; icon: LucideIcon; unavailable?: boolean }) {
  return <article className="metric sales-kpi-card"><div className="sales-kpi-label"><span className="sales-kpi-icon"><Icon size={16} /></span><span>{label}</span></div><strong>{value}</strong><small>{unavailable ? description : comparison ? `${comparison} vs previous period · ${description}` : description}</small></article>;
}

function StateCard({ title, message, action, error = false }: { title: string; message: string; action?: React.ReactNode; error?: boolean }) {
  return <div className={`sales-state-card card ${error ? 'sales-state-error' : ''}`}><div className="sales-state-icon"><Building2 size={24} /></div><p className="eyebrow">CRM & Sales</p><h2>{title}</h2><p className="muted">{message}</p>{action && <div className="sales-state-actions">{action}</div>}</div>;
}

function SalesFilterToolbar({ companyId, companyRows, branchId, branchRows, period, dateFrom, dateTo, dateError, fetching, onChange, onRefresh }: { companyId: string; companyRows: Array<{ id: string; name: string }>; branchId: string; branchRows: Array<{ id: string; name: string }>; period: SalesPeriod; dateFrom: string; dateTo: string; dateError?: string; fetching: boolean; onChange: (updates: Record<string, string | undefined>) => void; onRefresh: () => void }) {
  return <div className="sales-filter-toolbar card"><label>Company<select aria-label="Select sales company" value={companyId} onChange={(event) => onChange({ company_id: event.target.value, branch_id: undefined })}>{companyRows.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label><label>Branch<select aria-label="Select sales branch" value={branchId} disabled={!companyId} onChange={(event) => onChange({ branch_id: event.target.value })}><option value="">All branches</option>{branchRows.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label><label>Date range<select aria-label="Select sales date range" value={period} onChange={(event) => onChange({ period: event.target.value, date_from: event.target.value === 'custom' ? dateFrom : undefined, date_to: event.target.value === 'custom' ? dateTo : undefined })}>{periods.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>{period === 'custom' && <><label>From<input aria-label="Sales period start" type="date" value={dateFrom} onChange={(event) => onChange({ date_from: event.target.value })} /></label><label>To<input aria-label="Sales period end" type="date" value={dateTo} onChange={(event) => onChange({ date_to: event.target.value })} /></label></>}<button className="icon-action sales-refresh" type="button" aria-label="Refresh sales overview" onClick={onRefresh} disabled={fetching}><RefreshCw size={15} /></button>{dateError && <span className="error-text sales-date-error" role="alert">{dateError}</span>}</div>;
}

function SalesChart({ title, subtitle, rows, valueKey, currency, configured }: { title: string; subtitle: string; rows: Array<{ label: string; revenue?: string; expenses?: string; value?: string }>; valueKey: 'revenue' | 'value'; currency: string | null; configured: boolean }) {
  const values = rows.map((row) => Number(row[valueKey] ?? 0));
  const max = Math.max(...values, 1);
  return <article className="sales-panel card"><div className="panel-heading"><div><p className="eyebrow">Analytics</p><h2>{title}</h2><span className="muted">{subtitle}</span></div><BarChart3 size={17} className="muted" /></div>{rows.length === 0 ? <div className="sales-empty-inline">No verified data is available for this period.</div> : <div className="sales-chart"><div className="sales-chart-bars">{rows.map((row, index) => <div className="sales-chart-column" key={`${row.label}-${index}`}><div className="sales-chart-bar-wrap"><span className="sales-chart-bar" style={{ height: `${Math.max((values[index] / max) * 100, values[index] > 0 ? 7 : 2)}%` }} title={`${row.label}: ${money(row[valueKey], currency, configured)}`} /></div><small>{row.label}</small></div>)}</div><div className="sales-chart-legend"><span><i className="sales-legend-current" />{valueKey === 'revenue' ? 'Revenue' : 'Sales'}</span><strong>{money(String(values.reduce((sum, value) => sum + value, 0)), currency, configured)}</strong></div></div>}</article>;
}

function UnavailablePanel({ title, message, icon: Icon = Target }: { title: string; message: string; icon?: LucideIcon }) {
  return <article className="sales-panel card sales-unavailable-panel"><div className="panel-heading"><div><p className="eyebrow">Unavailable</p><h2>{title}</h2></div><Icon size={17} className="muted" /></div><div className="sales-empty-inline">{message}</div></article>;
}

function SalesActivity({ rows, currency, configured }: { rows: DashboardOverview['recent_sales']; currency: string | null; configured: boolean }) {
  return <article className="sales-panel card sales-table-panel"><div className="panel-heading"><div><p className="eyebrow">Verified source data</p><h2>Recent sales activity</h2></div><span className="muted">{rows.length} shown</span></div><div className="sales-table-scroll"><table><thead><tr><th>Reference</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>{rows.map((row) => <tr key={row.id}><td><strong>{row.number}</strong></td><td>{row.customer || 'Not provided'}</td><td>{date(row.date)}</td><td>{money(row.amount, currency, configured)}</td><td><span className="status-pill">{row.status}</span></td></tr>)}{rows.length === 0 && <tr><td colSpan={5} className="muted">No sales activity in the selected period.</td></tr>}</tbody></table></div></article>;
}

function TopProducts({ rows, currency, configured }: { rows: DashboardOverview['top_products']; currency: string | null; configured: boolean }) {
  return <article className="sales-panel card sales-table-panel"><div className="panel-heading"><div><p className="eyebrow">Product performance</p><h2>Top products by sales</h2></div><TrendingUp size={17} className="muted" /></div><div className="sales-table-scroll"><table><thead><tr><th>Product</th><th>SKU</th><th>Units</th><th>Revenue</th></tr></thead><tbody>{rows.map((row) => <tr key={`${row.product}-${row.sku}`}><td><strong>{row.product}</strong></td><td>{row.sku}</td><td>{number(row.units_sold)}</td><td>{money(row.revenue, currency, configured)}</td></tr>)}{rows.length === 0 && <tr><td colSpan={4} className="muted">No product sales in the selected period.</td></tr>}</tbody></table></div></article>;
}

function SalesQuickActions() {
  return <article className="sales-panel card"><div className="panel-heading"><div><p className="eyebrow">Navigation</p><h2>Quick actions</h2></div><WalletCards size={17} className="muted" /></div><div className="sales-quick-actions"><Link className="sales-quick-action" to="/workflow"><FileText size={15} /><span>Open reports workspace</span></Link><span className="sales-quick-action disabled"><Users size={15} /><span>New customer · Phase 2</span></span><span className="sales-quick-action disabled"><ShoppingCart size={15} /><span>New sales order · Phase 2</span></span><span className="sales-quick-action disabled"><CreditCard size={15} /><span>Record payment · Phase 3</span></span></div></article>;
}

export function CrmSalesPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const companies = useQuery({ queryKey: ['dashboard-companies', 'sales-overview'], queryFn: getDashboardCompanies });
  const permissions = useQuery({ queryKey: ['me', 'sales-overview'], queryFn: getMe, retry: false });
  const companyRows = companies.data?.data.data ?? [];
  const companyId = searchParams.get('company_id') ?? companyRows[0]?.id ?? '';
  const branchId = searchParams.get('branch_id') ?? '';
  const period = readPeriod(searchParams.get('period'));
  const dateFrom = searchParams.get('date_from') ?? '';
  const dateTo = searchParams.get('date_to') ?? '';
  const dateError = period === 'custom' && dateFrom && dateTo && dateFrom > dateTo ? 'Start date must be before or equal to end date.' : undefined;
  const branches = useQuery({ queryKey: ['dashboard-branches', 'sales-overview', companyId], queryFn: () => getDashboardBranches(companyId), enabled: Boolean(companyId) });
  const branchRows = useMemo(() => branches.data?.data.data ?? [], [branches.data]);
  const overviewParams = useMemo(() => ({ period, company_id: companyId || undefined, branch_id: branchId || undefined, ...(period === 'custom' ? { date_from: dateFrom || undefined, date_to: dateTo || undefined } : {}) }), [branchId, companyId, dateFrom, dateTo, period]);
  const overview = useQuery({ queryKey: ['sales-overview', overviewParams], queryFn: () => getDashboardOverview(overviewParams), enabled: Boolean(companyId) && (period !== 'custom' || Boolean(dateFrom && dateTo && !dateError)) });
  const granted = permissions.data?.data.permissions ?? [];
  const canManage = granted.includes('sales.manage') || granted.includes('organization.manage');

  useEffect(() => { if (!searchParams.get('company_id') && companyId) { const next = new URLSearchParams(searchParams); next.set('company_id', companyId); setSearchParams(next, { replace: true }); } }, [companyId, searchParams, setSearchParams]);
  useEffect(() => { if (branchId && !branchRows.some((branch) => branch.id === branchId)) { const next = new URLSearchParams(searchParams); next.delete('branch_id'); setSearchParams(next, { replace: true }); } }, [branchId, branchRows, searchParams, setSearchParams]);
  const updateFilters = (updates: Record<string, string | undefined>) => { const next = new URLSearchParams(searchParams); Object.entries(updates).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key)); setSearchParams(next, { replace: true }); };
  const activeCount = companies.data?.meta?.active_count ?? companyRows.length;
  const inactiveCount = companies.data?.meta?.inactive_count ?? 0;
  const data = overview.data?.data;
  const unavailableReason = 'The verified sales payment aggregate endpoint does not support company, branch, and date filters yet.';
  const receivablesReason = 'Customer balance reporting is not yet scoped for this workspace dashboard.';
  const kpis: Array<{ label: string; value: string; comparison?: string | null; description: string; icon: LucideIcon; unavailable?: boolean }> = data ? [
    { label: 'Total sales', value: money(data.kpis.revenue.value, data.currency.code, data.currency.configured), comparison: data.kpis.revenue.change_percent, description: 'Recognized revenue', icon: TrendingUp },
    { label: 'Sales orders', value: number(data.kpis.sales_orders.value), comparison: data.kpis.sales_orders.change_percent, description: 'Qualifying orders', icon: ShoppingCart },
    { label: 'Pending invoices', value: number(data.kpis.pending_invoices.value), comparison: data.kpis.pending_invoices.change_percent, description: 'Outstanding invoice count', icon: Receipt },
    { label: 'Pending deliveries', value: number(data.kpis.pending_deliveries.value), comparison: data.kpis.pending_deliveries.change_percent, description: 'Fulfillment still due', icon: Truck },
    { label: 'Payments received', value: '—', description: unavailableReason, icon: CreditCard, unavailable: true },
    { label: 'Outstanding receivables', value: '—', description: receivablesReason, icon: WalletCards, unavailable: true },
  ] : [];

  if (companies.isLoading) return <section className="page-section sales-page"><div className="dashboard-loading" role="status">Loading authorized sales companies…</div></section>;
  if (companies.isError) return <section className="page-section sales-page"><StateCard title="Sales workspace unavailable" message={companies.error instanceof ApiError && companies.error.status === 403 ? 'Your account does not have access to an active company.' : companies.error.message} error action={<button className="secondary" type="button" onClick={() => void companies.refetch()}><RefreshCw size={14} />Retry</button>} /></section>;
  if (!companyId) return <section className="page-section sales-page"><StateCard title={inactiveCount > 0 ? 'Your company is inactive' : 'Set up your company'} message={inactiveCount > 0 ? 'Sales data is unavailable until an authorized company is active.' : 'Create an active company before using the Sales workspace.'} action={inactiveCount === 0 ? <Link className="primary" to="/company"><Building2 size={15} />Open company setup</Link> : undefined} /></section>;

  return <section className="page-section sales-page">
    <div className="sales-page-heading"><div><div className="breadcrumb-line"><span>CRM & Sales</span><ChevronRight size={13} /><span>Overview</span></div><h2>Sales overview</h2><p className="muted">Track verified sales activity, recognized revenue, and fulfillment signals for the selected company context.</p></div><div className="sales-page-actions">{canManage && <span className="sales-unavailable-action" title="Sales order creation UI is planned for Phase 2"><ShoppingCart size={14} />New order · Phase 2</span>}</div></div>
    <SalesFilterToolbar companyId={companyId} companyRows={companyRows} branchId={branchId} branchRows={branchRows} period={period} dateFrom={dateFrom} dateTo={dateTo} dateError={dateError} fetching={overview.isFetching} onChange={updateFilters} onRefresh={() => void overview.refetch()} />
    {activeCount === 0 && inactiveCount > 0 && <StateCard title="No active company" message="The workspace contains company records, but none are active for sales reporting." />}
    {activeCount > 0 && overview.isError && <div className="alert alert-danger" role="alert">Unable to load sales overview. {overview.error.message}<button className="table-action" type="button" onClick={() => void overview.refetch()}>Retry</button></div>}
    {activeCount > 0 && overview.isLoading && <div className="sales-loading-grid" role="status">{Array.from({ length: 6 }, (_, index) => <div className="sales-skeleton" key={index} />)}</div>}
    {activeCount > 0 && data && !overview.isError && <><div className="sales-kpi-grid">{kpis.map((kpi) => <SalesKpiCard key={kpi.label} {...kpi} />)}</div><div className="sales-analytics-grid"><SalesChart title="Revenue overview" subtitle="Recognized revenue by reporting period" rows={data.charts.revenue_expenses.map((row) => ({ label: row.label, revenue: row.revenue }))} valueKey="revenue" currency={data.currency.code} configured={data.currency.configured} /><SalesChart title="Monthly sales trend" subtitle="Qualifying sales activity" rows={data.charts.sales_trend.map((row) => ({ label: row.label, value: row.value }))} valueKey="value" currency={data.currency.code} configured={data.currency.configured} /><UnavailablePanel title="Sales pipeline" message="Lead and opportunity data is not implemented in the current sales workspace." /></div><div className="sales-analytics-grid sales-secondary-analytics"><UnavailablePanel title="Sales by customer" message="Customer ranking requires a scoped sales analytics endpoint." icon={Users} /><TopProducts rows={data.top_products} currency={data.currency.code} configured={data.currency.configured} /><UnavailablePanel title="Performance by branch" message="Branch comparison is unavailable until a verified branch sales aggregate is exposed." icon={Building2} /></div><div className="sales-business-grid"><SalesActivity rows={data.recent_sales} currency={data.currency.code} configured={data.currency.configured} /><TopProducts rows={data.top_products_by_volume} currency={data.currency.code} configured={data.currency.configured} /><UnavailablePanel title="Follow-up activities" message="Follow-up activity records are not implemented." icon={Users} /></div><div className="sales-business-grid"><UnavailablePanel title="Pending invoices" message={`${number(data.kpis.pending_invoices.value)} qualifying invoices are pending. Invoice detail rows are not yet available in the Sales UI.`} icon={Receipt} /><UnavailablePanel title="Pending deliveries" message={`${number(data.kpis.pending_deliveries.value)} fulfillment records remain pending. Delivery detail rows are not yet available in the Sales UI.`} icon={Truck} /><SalesQuickActions /></div></>}
  </section>;
}
