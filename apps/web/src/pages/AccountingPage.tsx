import { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, BookOpen, CalendarDays, Coins, RefreshCw, Settings2 } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { getAccountingCurrencies, getDashboardCompanies, getFiscalPeriods, getLedgerAccounts } from '../lib/api';

export function AccountingPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const companies = useQuery({ queryKey: ['accounting-companies'], queryFn: getDashboardCompanies });
  const companyRows = useMemo(() => companies.data?.data.data ?? [], [companies.data]);
  const selectedCompanyId = searchParams.get('company_id') ?? (companyRows.length === 1 ? companyRows[0].id : '');
  const currencies = useQuery({ queryKey: ['accounting-currencies'], queryFn: getAccountingCurrencies });
  const accounts = useQuery({ queryKey: ['ledger-accounts', selectedCompanyId], queryFn: () => getLedgerAccounts(selectedCompanyId), enabled: Boolean(selectedCompanyId) });
  const periods = useQuery({ queryKey: ['fiscal-periods', selectedCompanyId], queryFn: () => getFiscalPeriods(selectedCompanyId), enabled: Boolean(selectedCompanyId) });
  const company = companyRows.find((item) => item.id === selectedCompanyId);
  const accountRows = accounts.data?.data.data ?? [];
  const periodRows = periods.data?.data.data ?? [];
  const baseCurrency = company?.base_currency ? company.base_currency.toUpperCase() : '';
  const currencyReady = Boolean(baseCurrency && currencies.data?.data.some((currency) => currency.code === baseCurrency));
  const setupReady = currencyReady && accountRows.length > 0 && periodRows.length > 0;
  const isLoading = companies.isLoading || currencies.isLoading || accounts.isLoading || periods.isLoading;
  const error = companies.error ?? currencies.error ?? accounts.error ?? periods.error;
  const refresh = () => { void companies.refetch(); void currencies.refetch(); void accounts.refetch(); void periods.refetch(); };

  useEffect(() => { if (!searchParams.get('company_id') && companyRows.length === 1) setSearchParams({ company_id: companyRows[0].id }, { replace: true }); }, [companyRows, searchParams, setSearchParams]);

  return <section className="accounting-page">
    <header className="accounting-page-header"><div><h1>Accounting</h1><p>Financial setup and posted-ledger readiness.</p></div><div className="accounting-actions"><select aria-label="Accounting company" value={selectedCompanyId} onChange={(event) => setSearchParams(event.target.value ? { company_id: event.target.value } : {})}><option value="">Select company</option>{companyRows.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><button className="accounting-refresh" type="button" onClick={refresh} disabled={isLoading} aria-label="Refresh accounting"><RefreshCw size={16} className={isLoading ? 'is-spinning' : ''} /> Refresh</button></div></header>
    {error && <div className="accounting-alert danger" role="alert">Unable to load accounting data. <button type="button" onClick={refresh}>Retry</button></div>}
    {!error && !selectedCompanyId && !companies.isLoading && <div className="accounting-state"><BookOpen size={22} /><strong>Select an authorized company</strong><span>Accounting records are isolated by company.</span></div>}
    {selectedCompanyId && !error && <>
      {!currencyReady && <div className="accounting-alert warning"><AlertTriangle size={17} /><span>Base accounting currency is required before financial totals can be displayed.</span><Link to={`/organization/profile?company_id=${encodeURIComponent(selectedCompanyId)}`}>Configure currency</Link></div>}
      <div className="accounting-setup-grid">
        <article className={`accounting-setup-card ${currencyReady ? 'ready' : 'missing'}`}><span className="accounting-setup-icon blue"><Coins size={18} /></span><div><small>Base currency</small><strong>{isLoading ? '…' : baseCurrency || 'Not configured'}</strong><em>{currencyReady ? 'Ready for financial totals' : 'Required in company profile'}</em></div></article>
        <article className={`accounting-setup-card ${accountRows.length ? 'ready' : 'missing'}`}><span className="accounting-setup-icon indigo"><BookOpen size={18} /></span><div><small>Chart of accounts</small><strong>{isLoading ? '…' : accountRows.length}</strong><em>{accountRows.length ? 'Accounts available' : 'No accounts configured'}</em></div></article>
        <article className={`accounting-setup-card ${periodRows.length ? 'ready' : 'missing'}`}><span className="accounting-setup-icon purple"><CalendarDays size={18} /></span><div><small>Fiscal periods</small><strong>{isLoading ? '…' : periodRows.length}</strong><em>{periodRows.filter((period) => period.state === 'open').length} open</em></div></article>
        <article className={`accounting-setup-card ${setupReady ? 'ready' : 'missing'}`}><span className="accounting-setup-icon gray"><Settings2 size={18} /></span><div><small>Posting readiness</small><strong>{isLoading ? '…' : setupReady ? 'Ready' : 'Blocked'}</strong><em>{setupReady ? 'Core setup is present' : 'Complete required setup'}</em></div></article>
      </div>
      <div className="accounting-content-grid">
        <article className="accounting-panel"><div className="accounting-panel-heading"><div><h2>Chart of accounts</h2><span>{accountRows.length} accounts</span></div>{!accountRows.length && <Link className="accounting-link" to="/organization/profile">Open setup</Link>}</div>{isLoading && <div className="accounting-state compact">Loading accounting setup…</div>}{!isLoading && !accountRows.length && <div className="accounting-state compact"><BookOpen size={20} /><strong>No accounts configured</strong><span>Posting remains blocked until an authorized administrator configures the chart of accounts.</span></div>}{!isLoading && accountRows.length > 0 && <div className="accounting-table-scroll"><table className="accounting-table"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Normal balance</th><th>Status</th></tr></thead><tbody>{accountRows.map((account) => <tr key={account.id}><td><code>{account.code}</code></td><td><strong>{account.name}</strong></td><td>{account.type}</td><td>{account.normal_balance}</td><td><span className={`accounting-status ${account.is_active ? 'active' : 'inactive'}`}>{account.is_active ? 'Active' : 'Inactive'}</span></td></tr>)}</tbody></table></div>}</article>
        <article className="accounting-panel"><div className="accounting-panel-heading"><div><h2>Fiscal periods</h2><span>{periodRows.length} configured</span></div></div>{!isLoading && !periodRows.length && <div className="accounting-state compact"><CalendarDays size={20} /><strong>No fiscal periods configured</strong><span>Posting requires an open period covering the entry date.</span></div>}{!isLoading && periodRows.length > 0 && <div className="accounting-period-list">{periodRows.slice(0, 8).map((period) => <div className="accounting-period-row" key={period.id}><span><strong>{period.name}</strong><small>{period.starts_on} — {period.ends_on}</small></span><span className={`accounting-status ${period.state}`}>{period.state}</span></div>)}</div>}</article>
      </div>
    </>}
  </section>;
}
