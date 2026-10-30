import { RefreshCw } from 'lucide-react';
import type { Branch, Company, Tenant } from '../lib/api';

export type DashboardPeriod = 'today' | 'last_7_days' | 'last_30_days' | 'this_month' | 'this_year' | 'custom';
const periods: Array<{ value: DashboardPeriod; label: string }> = [
  { value: 'today', label: 'Today' },
  { value: 'last_7_days', label: 'Last 7 days' },
  { value: 'last_30_days', label: 'Last 30 days' },
  { value: 'this_month', label: 'This month' },
  { value: 'this_year', label: 'This year' },
  { value: 'custom', label: 'Custom range' },
];

type Props = {
  tenants: Tenant[];
  tenantId: string;
  period: DashboardPeriod;
  companyId: string;
  branchId: string;
  dateFrom: string;
  dateTo: string;
  companies: Company[];
  branches: Branch[];
  dateError?: string;
  isFetching: boolean;
  onPeriodChange: (value: DashboardPeriod) => void;
  onTenantChange: (value: string) => void;
  onCompanyChange: (value: string) => void;
  onBranchChange: (value: string) => void;
  onDateFromChange: (value: string) => void;
  onDateToChange: (value: string) => void;
  onRefresh: () => void;
};

export function DashboardFilterBar({ tenantId, period, companyId, branchId, dateFrom, dateTo, tenants, companies, branches, dateError, isFetching, onTenantChange, onPeriodChange, onCompanyChange, onBranchChange, onDateFromChange, onDateToChange, onRefresh }: Props) {
  return <div className="dashboard-filter-region"><div className="dashboard-toolbar" aria-label="Dashboard filters">
    {tenants.length > 1 && <label>Workspace<select aria-label="Select workspace" value={tenantId} onChange={(event) => onTenantChange(event.target.value)}>{tenants.map((tenant) => <option value={tenant.id} key={tenant.id}>{tenant.name}</option>)}</select></label>}
    <label>Company<select aria-label="Filter by company" value={companyId} onChange={(event) => onCompanyChange(event.target.value)}><option value="">All companies</option>{companies.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}</select></label>
    <label>Branch<select aria-label="Filter by branch" value={branchId} onChange={(event) => onBranchChange(event.target.value)} disabled={!companyId}><option value="">All branches</option>{companyId && branches.length === 0 && <option value="" disabled>No branches configured</option>}{branches.map((branch) => <option value={branch.id} key={branch.id}>{branch.name}</option>)}</select>{companyId && branches.length === 0 && <small className="muted">No branches configured</small>}</label>
    <label className="period-filter">Date range<select aria-label="Filter by date range" value={period} onChange={(event) => onPeriodChange(event.target.value as DashboardPeriod)}>{periods.map((item) => <option value={item.value} key={item.value}>{item.label}</option>)}</select></label>
    {period === 'custom' && <><label>Start date<input aria-label="Custom start date" type="date" value={dateFrom} max={dateTo || undefined} onChange={(event) => onDateFromChange(event.target.value)} /></label><label>End date<input aria-label="Custom end date" type="date" value={dateTo} min={dateFrom || undefined} onChange={(event) => onDateToChange(event.target.value)} /></label></>}
    <button type="button" className={`icon-button refresh-dashboard ${isFetching ? 'is-spinning' : ''}`} aria-label="Refresh dashboard" title={isFetching ? 'Refreshing dashboard' : 'Refresh dashboard'} onClick={onRefresh}><RefreshCw size={16} aria-hidden="true" /></button>
  </div>{dateError ? <p className="dashboard-filter-error" role="alert">{dateError}</p> : null}</div>;
}
