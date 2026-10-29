import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { DashboardFoundation, DashboardFoundationLoading } from '../components/DashboardFoundation';
import { getBranches, getCompanies, getDashboardOverview } from '../lib/api';

type Period = 'today' | 'last_7_days' | 'last_30_days' | 'this_month' | 'this_year' | 'custom';
const periods: Array<{ value: Period; label: string }> = [
  { value: 'today', label: 'Today' },
  { value: 'last_7_days', label: 'Last 7 days' },
  { value: 'last_30_days', label: 'Last 30 days' },
  { value: 'this_month', label: 'This month' },
  { value: 'this_year', label: 'This year' },
  { value: 'custom', label: 'Custom range' },
];

export function CompanyDashboard() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [period, setPeriod] = useState<Period>('last_30_days');
  const [companyId, setCompanyId] = useState('');
  const [branchId, setBranchId] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const companies = useQuery({ queryKey: ['companies', 'dashboard'], queryFn: getCompanies });
  const companyRows = companies.data?.data.data ?? [];
  const branches = useQuery({ queryKey: ['branches', companyId], queryFn: () => getBranches(companyId), enabled: Boolean(companyId) });
  const branchRows = branches.data?.data.data ?? [];
  const dashboard = useQuery({ queryKey: ['dashboard-overview', period, companyId, branchId, from, to], queryFn: () => getDashboardOverview({ period, company_id: companyId || undefined, branch_id: branchId || undefined, ...(period === 'custom' ? { from, to } : {}) }), enabled: period !== 'custom' || Boolean(from && to) });

  useEffect(() => {
    setCompanyId(searchParams.get('company_id') ?? '');
    setBranchId(searchParams.get('branch_id') ?? '');
  }, [searchParams]);

  return (
    <section className="page-section dashboard-page foundation-page">
      <div className="dashboard-welcome">
        <div>
          <p className="eyebrow">Company workspace</p>
          <h1>Good morning</h1>
          <p className="muted">A clear operating view for your company workspace.</p>
        </div>
        <div className="dashboard-meta"><span className="status-dot" />Dashboard foundation <span>·</span> Phase 2 data ready</div>
      </div>

      <div className="dashboard-toolbar">
        <label>Company<select value={companyId} onChange={(event) => { setCompanyId(event.target.value); setBranchId(''); }}><option value="">All companies</option>{companyRows.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}</select></label>
        <label>Branch<select value={branchId} onChange={(event) => setBranchId(event.target.value)} disabled={!companyId}><option value="">All branches</option>{branchRows.map((branch) => <option value={branch.id} key={branch.id}>{branch.name}</option>)}</select></label>
        <label className="period-filter">Date range<select value={period} onChange={(event) => setPeriod(event.target.value as Period)}>{periods.map((item) => <option value={item.value} key={item.value}>{item.label}</option>)}</select></label>
        {period === 'custom' && <><label>From<input type="date" value={from} onChange={(event) => setFrom(event.target.value)} /></label><label>To<input type="date" value={to} onChange={(event) => setTo(event.target.value)} /></label></>}
        <button className={`icon-button refresh-dashboard ${dashboard.isFetching ? 'is-spinning' : ''}`} aria-label="Refresh dashboard" onClick={() => void dashboard.refetch()}><RefreshCw size={16} /></button>
      </div>

      {dashboard.isError && <div className="alert alert-danger dashboard-alert" role="alert">Unable to load dashboard data. Check your access and try again. <button className="table-action" onClick={() => void dashboard.refetch()}>Retry</button></div>}
      {dashboard.isLoading && <DashboardFoundationLoading />}
      {dashboard.data && <DashboardFoundation data={dashboard.data.data} onNavigate={navigate} />}
      {!dashboard.isLoading && !dashboard.isError && !dashboard.data && period === 'custom' && <div className="dashboard-loading" role="status">Select both start and end dates to load dashboard data.</div>}
    </section>
  );
}
