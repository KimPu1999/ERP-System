import { RefreshCw } from 'lucide-react';
import type { Branch, Company, Tenant } from '../lib/api';
import { useI18n } from '../lib/i18n';

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
  const { t } = useI18n();
  const periodLabels: Record<DashboardPeriod, string> = { today: t('dashboard.today'), last_7_days: t('dashboard.last7'), last_30_days: t('dashboard.last30'), this_month: t('dashboard.thisMonth'), this_year: t('dashboard.thisYear'), custom: t('dashboard.customRange') };
  return <div className="dashboard-filter-region"><div className="dashboard-toolbar" aria-label={t('dashboard.filters')}>
    {tenants.length > 1 && <label>{t('dashboard.workspace')}<select aria-label={t('dashboard.workspace')} value={tenantId} onChange={(event) => onTenantChange(event.target.value)}>{tenants.map((tenant) => <option value={tenant.id} key={tenant.id}>{tenant.name}</option>)}</select></label>}
    <label>{t('dashboard.company')}<select aria-label={t('dashboard.company')} value={companyId} onChange={(event) => onCompanyChange(event.target.value)}><option value="">{t('app.allCompanies')}</option>{companies.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}</select></label>
    <label>{t('dashboard.branch')}<select aria-label={t('dashboard.branch')} value={branchId} onChange={(event) => onBranchChange(event.target.value)} disabled={!companyId}><option value="">{t('app.allBranches')}</option>{companyId && branches.length === 0 && <option value="" disabled>{t('dashboard.noBranches')}</option>}{branches.map((branch) => <option value={branch.id} key={branch.id}>{branch.name}</option>)}</select>{companyId && branches.length === 0 && <small className="muted">{t('dashboard.noBranches')}</small>}</label>
    <label className="period-filter">{t('dashboard.dateRange')}<select aria-label={t('dashboard.dateRange')} value={period} onChange={(event) => onPeriodChange(event.target.value as DashboardPeriod)}>{periods.map((item) => <option value={item.value} key={item.value}>{periodLabels[item.value]}</option>)}</select></label>
    {period === 'custom' && <><label>{t('dashboard.startDate')}<input aria-label={t('dashboard.startDate')} type="date" value={dateFrom} max={dateTo || undefined} onChange={(event) => onDateFromChange(event.target.value)} /></label><label>{t('dashboard.endDate')}<input aria-label={t('dashboard.endDate')} type="date" value={dateTo} min={dateFrom || undefined} onChange={(event) => onDateToChange(event.target.value)} /></label></>}
    <button type="button" className={`icon-button refresh-dashboard ${isFetching ? 'is-spinning' : ''}`} aria-label={t('dashboard.refresh')} title={isFetching ? t('dashboard.refreshing') : t('dashboard.refresh')} onClick={onRefresh}><RefreshCw size={16} aria-hidden="true" /></button>
  </div>{dateError ? <p className="dashboard-filter-error" role="alert">{dateError}</p> : null}</div>;
}
