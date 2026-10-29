import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { DashboardFilterBar, type DashboardPeriod } from '../components/DashboardFilterBar';
import { DashboardFoundation, DashboardFoundationLoading } from '../components/DashboardFoundation';
import { clearActiveTenantId, getActiveTenantId, getDashboardBranches, getDashboardCompanies, getDashboardFinancialPerformance, getDashboardGrowth, getDashboardOverview, getDashboardRecentTransactions, getDashboardRevenueTrend, getDashboardTopProducts, getTenants, setActiveTenantId } from '../lib/api';

const validPeriods: DashboardPeriod[] = ['today', 'last_7_days', 'last_30_days', 'this_month', 'this_year', 'custom'];
const readPeriod = (value: string | null): DashboardPeriod => value && validPeriods.includes(value as DashboardPeriod) ? value as DashboardPeriod : 'last_30_days';

export function CompanyDashboard() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [tenantId, setTenantId] = useState(() => searchParams.get('tenant_id') ?? getActiveTenantId() ?? '');
  const period = readPeriod(searchParams.get('period'));
  const companyId = searchParams.get('company_id') ?? '';
  const branchId = searchParams.get('branch_id') ?? '';
  const analyticsYear = searchParams.get('analytics_year') ?? '';
  const dateFrom = searchParams.get('date_from') ?? searchParams.get('from') ?? '';
  const dateTo = searchParams.get('date_to') ?? searchParams.get('to') ?? '';
  const dateError = period === 'custom' && dateFrom && dateTo && dateFrom > dateTo ? 'Start date must be on or before the end date.' : undefined;

  const tenants = useQuery({ queryKey: ['tenants', 'current-user'], queryFn: getTenants, retry: false });
  const tenantRows = useMemo(() => tenants.data?.data ?? [], [tenants.data]);
  const tenantReady = !tenants.isPending && Boolean(tenantId) && tenantRows.some((tenant) => tenant.id === tenantId);
  const tenantError = tenants.isError ? tenants.error.message : undefined;
  const companies = useQuery({ queryKey: ['companies', 'dashboard', tenantId], queryFn: getDashboardCompanies, enabled: tenantReady });
  const companyRows = useMemo(() => companies.data?.data.data ?? [], [companies.data]);
  const branches = useQuery({ queryKey: ['branches', 'dashboard', tenantId, companyId], queryFn: () => getDashboardBranches(companyId), enabled: tenantReady && Boolean(companyId) });
  const branchRows = useMemo(() => branches.data?.data.data ?? [], [branches.data]);
  const dashboard = useQuery({ queryKey: ['dashboard-overview', tenantId, period, companyId, branchId, dateFrom, dateTo], queryFn: () => getDashboardOverview({ period, company_id: companyId || undefined, branch_id: branchId || undefined, ...(period === 'custom' ? { date_from: dateFrom, date_to: dateTo } : {}) }), enabled: tenantReady && (period !== 'custom' || Boolean(dateFrom && dateTo && !dateError)) });
  const analyticsParams = useMemo(() => ({ period, company_id: companyId || undefined, branch_id: branchId || undefined, ...(period === 'custom' ? { date_from: dateFrom, date_to: dateTo } : {}), ...(analyticsYear ? { year: analyticsYear, comparison_period: 'previous_year' as const } : { comparison_period: 'previous_period' as const }) }), [analyticsYear, branchId, companyId, dateFrom, dateTo, period]);
  const analyticsEnabled = tenantReady && (Boolean(analyticsYear) || period !== 'custom' || Boolean(dateFrom && dateTo && !dateError));
  const revenueAnalytics = useQuery({ queryKey: ['dashboard-revenue-trend', tenantId, analyticsParams], queryFn: () => getDashboardRevenueTrend(analyticsParams), enabled: analyticsEnabled });
  const growthAnalytics = useQuery({ queryKey: ['dashboard-growth', tenantId, analyticsParams], queryFn: () => getDashboardGrowth(analyticsParams), enabled: analyticsEnabled });
  const financialAnalytics = useQuery({ queryKey: ['dashboard-financial-performance', tenantId, analyticsParams], queryFn: () => getDashboardFinancialPerformance(analyticsParams), enabled: analyticsEnabled });
  const businessParams = useMemo(() => ({ period, company_id: companyId || undefined, branch_id: branchId || undefined, ...(period === 'custom' ? { date_from: dateFrom, date_to: dateTo } : {}), ...(analyticsYear ? { year: analyticsYear } : {}), limit: '8' }), [analyticsYear, branchId, companyId, dateFrom, dateTo, period]);
  const businessEnabled = tenantReady && (Boolean(analyticsYear) || period !== 'custom' || Boolean(dateFrom && dateTo && !dateError));
  const recentTransactions = useQuery({ queryKey: ['dashboard-recent-transactions', tenantId, businessParams], queryFn: () => getDashboardRecentTransactions(businessParams), enabled: businessEnabled });
  const topProductsSales = useQuery({ queryKey: ['dashboard-top-products-sales', tenantId, businessParams], queryFn: () => getDashboardTopProducts(businessParams, 'sales'), enabled: businessEnabled });
  const topProductsVolume = useQuery({ queryKey: ['dashboard-top-products-volume', tenantId, businessParams], queryFn: () => getDashboardTopProducts(businessParams, 'volume'), enabled: businessEnabled });

  useEffect(() => {
    if (tenants.isPending) return;
    const selected = tenantRows.some((tenant) => tenant.id === tenantId) ? tenantId : tenantRows[0]?.id ?? '';
    if (selected) {
      setActiveTenantId(selected);
      if (selected !== tenantId) setTenantId(selected);
      if (searchParams.get('tenant_id') !== selected) {
        const next = new URLSearchParams(searchParams);
        next.set('tenant_id', selected);
        next.delete('company_id');
        next.delete('branch_id');
        setSearchParams(next, { replace: true });
      }
    } else {
      clearActiveTenantId();
      setTenantId('');
    }
  }, [searchParams, setSearchParams, tenantId, tenantRows, tenants.isPending]);

  useEffect(() => {
    if (!companyId || companyRows.some((company) => company.id === companyId)) return;
    const next = new URLSearchParams(searchParams);
    next.delete('company_id');
    next.delete('branch_id');
    setSearchParams(next, { replace: true });
  }, [companyId, companyRows, searchParams, setSearchParams]);

  useEffect(() => {
    if (!branchId || branchRows.some((branch) => branch.id === branchId)) return;
    const next = new URLSearchParams(searchParams);
    next.delete('branch_id');
    setSearchParams(next, { replace: true });
  }, [branchId, branchRows, searchParams, setSearchParams]);

  const updateFilter = (updates: Record<string, string | undefined>) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(updates).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key));
    setSearchParams(next, { replace: true });
  };

  return <section className="page-section dashboard-page foundation-page">
    <div className="dashboard-welcome"><div><p className="eyebrow">Company workspace</p><h1>Good morning</h1><p className="muted">A clear operating view for your company workspace.</p></div><div className="dashboard-meta"><span className="status-dot" />Live business data <span>·</span> {period === 'custom' ? 'Custom range' : 'Selected period'}</div></div>
    <DashboardFilterBar tenantId={tenantId} tenants={tenantRows} period={period} companyId={companyId} branchId={branchId} dateFrom={dateFrom} dateTo={dateTo} companies={companyRows} branches={branchRows} dateError={dateError} isFetching={dashboard.isFetching} onTenantChange={(value) => { setActiveTenantId(value); setTenantId(value); updateFilter({ tenant_id: value, company_id: undefined, branch_id: undefined }); }} onPeriodChange={(value) => updateFilter({ period: value, date_from: value === 'custom' ? dateFrom : undefined, date_to: value === 'custom' ? dateTo : undefined, from: undefined, to: undefined })} onCompanyChange={(value) => updateFilter({ company_id: value, branch_id: undefined })} onBranchChange={(value) => updateFilter({ branch_id: value })} onDateFromChange={(value) => updateFilter({ date_from: value, from: undefined })} onDateToChange={(value) => updateFilter({ date_to: value, to: undefined })} onRefresh={() => void dashboard.refetch()} />
    {tenantError && <div className="alert alert-danger dashboard-alert" role="alert">Unable to load your workspace context. {tenantError}</div>}
    {!tenantError && !tenants.isPending && tenantRows.length === 0 && <div className="dashboard-loading" role="status">No active workspace access is available for this account.</div>}
    {dashboard.isError && <div className="alert alert-danger dashboard-alert" role="alert">Unable to load dashboard data. {dashboard.error.message} <button className="table-action" onClick={() => void dashboard.refetch()}>Retry</button></div>}
    {dashboard.isLoading && <DashboardFoundationLoading />}
    {dashboard.data && !dashboard.isError && <DashboardFoundation data={dashboard.data.data} onNavigate={navigate} analytics={{ revenue: revenueAnalytics.data?.data, growth: growthAnalytics.data?.data, financial: financialAnalytics.data?.data, revenueError: revenueAnalytics.error instanceof Error ? revenueAnalytics.error : null, growthError: growthAnalytics.error instanceof Error ? growthAnalytics.error : null, financialError: financialAnalytics.error instanceof Error ? financialAnalytics.error : null, isLoading: revenueAnalytics.isLoading || growthAnalytics.isLoading || financialAnalytics.isLoading, year: analyticsYear, onYearChange: (value) => updateFilter({ analytics_year: value || undefined }), onRetry: () => { void revenueAnalytics.refetch(); void growthAnalytics.refetch(); void financialAnalytics.refetch(); } }} business={{ recent: recentTransactions.data?.data, sales: topProductsSales.data?.data, volume: topProductsVolume.data?.data, isLoading: recentTransactions.isLoading || topProductsSales.isLoading || topProductsVolume.isLoading, recentError: recentTransactions.error instanceof Error ? recentTransactions.error : null, salesError: topProductsSales.error instanceof Error ? topProductsSales.error : null, volumeError: topProductsVolume.error instanceof Error ? topProductsVolume.error : null, onRetry: () => { void recentTransactions.refetch(); void topProductsSales.refetch(); void topProductsVolume.refetch(); } }} />}
    {!dashboard.isLoading && !dashboard.isError && !dashboard.data && period === 'custom' && <div className="dashboard-loading" role="status">Select both start and end dates to load dashboard data.</div>}
  </section>;
}
