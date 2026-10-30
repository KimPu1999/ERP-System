import { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, BookOpen, CalendarDays, Coins, RefreshCw, Settings2 } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { getAccountingCurrencies, getDashboardCompanies, getFiscalPeriods, getLedgerAccounts } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function AccountingPage() {
  const { t } = useI18n();
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
    <header className="accounting-page-header"><div><h1>{t('accounting.title')}</h1><p>{t('accounting.description')}</p></div><div className="accounting-actions"><select aria-label={t('accounting.selectCompany')} value={selectedCompanyId} onChange={(event) => setSearchParams(event.target.value ? { company_id: event.target.value } : {})}><option value="">{t('accounting.selectCompany')}</option>{companyRows.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><button className="accounting-refresh" type="button" onClick={refresh} disabled={isLoading} aria-label={t('common.refresh')}><RefreshCw size={16} className={isLoading ? 'is-spinning' : ''} /> {t('common.refresh')}</button></div></header>
    {error && <div className="accounting-alert danger" role="alert">{t('accounting.error')} <button type="button" onClick={refresh}>{t('common.retry')}</button></div>}
    {!error && !selectedCompanyId && !companies.isLoading && <div className="accounting-state"><BookOpen size={22} /><strong>{t('common.selectCompany')}</strong><span>{t('inventory.noCompany')}</span></div>}
    {selectedCompanyId && !error && <>
      {!currencyReady && <div className="accounting-alert warning"><AlertTriangle size={17} /><span>{t('accounting.currencyRequired')}</span><Link to={`/organization/profile?company_id=${encodeURIComponent(selectedCompanyId)}`}>{t('accounting.configureCurrency')}</Link></div>}
      <div className="accounting-setup-grid">
        <article className={`accounting-setup-card ${currencyReady ? 'ready' : 'missing'}`}><span className="accounting-setup-icon blue"><Coins size={18} /></span><div><small>{t('accounting.baseCurrency')}</small><strong>{isLoading ? '…' : baseCurrency || t('common.notConfigured')}</strong><em>{currencyReady ? t('accounting.readyTotals') : t('accounting.requiredProfile')}</em></div></article>
        <article className={`accounting-setup-card ${accountRows.length ? 'ready' : 'missing'}`}><span className="accounting-setup-icon indigo"><BookOpen size={18} /></span><div><small>{t('accounting.chartOfAccounts')}</small><strong>{isLoading ? '…' : accountRows.length}</strong><em>{accountRows.length ? t('accounting.accountsAvailable') : t('accounting.noAccounts')}</em></div></article>
        <article className={`accounting-setup-card ${periodRows.length ? 'ready' : 'missing'}`}><span className="accounting-setup-icon purple"><CalendarDays size={18} /></span><div><small>{t('accounting.fiscalPeriods')}</small><strong>{isLoading ? '…' : periodRows.length}</strong><em>{periodRows.filter((period) => period.state === 'open').length} {t('accounting.openPeriods')}</em></div></article>
        <article className={`accounting-setup-card ${setupReady ? 'ready' : 'missing'}`}><span className="accounting-setup-icon gray"><Settings2 size={18} /></span><div><small>{t('accounting.postingReadiness')}</small><strong>{isLoading ? '…' : setupReady ? t('common.ready') : t('common.blocked')}</strong><em>{setupReady ? t('accounting.coreSetup') : t('accounting.completeSetup')}</em></div></article>
      </div>
      <div className="accounting-content-grid">
        <article className="accounting-panel"><div className="accounting-panel-heading"><div><h2>{t('accounting.chartOfAccounts')}</h2><span>{accountRows.length} {t('accounting.accounts')}</span></div>{!accountRows.length && <Link className="accounting-link" to="/organization/profile">{t('accounting.openSetup')}</Link>}</div>{isLoading && <div className="accounting-state compact">{t('accounting.loadingSetup')}</div>}{!isLoading && !accountRows.length && <div className="accounting-state compact"><BookOpen size={20} /><strong>{t('accounting.noAccounts')}</strong><span>{t('accounting.noAccountsDetail')}</span></div>}{!isLoading && accountRows.length > 0 && <div className="accounting-table-scroll"><table className="accounting-table"><thead><tr><th>{t('accounting.code')}</th><th>{t('accounting.name')}</th><th>{t('accounting.type')}</th><th>{t('accounting.normalBalance')}</th><th>{t('accounting.status')}</th></tr></thead><tbody>{accountRows.map((account) => <tr key={account.id}><td><code>{account.code}</code></td><td><strong>{account.name}</strong></td><td>{account.type}</td><td>{account.normal_balance}</td><td><span className={`accounting-status ${account.is_active ? 'active' : 'inactive'}`}>{account.is_active ? t('common.active') : t('common.inactive')}</span></td></tr>)}</tbody></table></div>}</article>
        <article className="accounting-panel"><div className="accounting-panel-heading"><div><h2>{t('accounting.fiscalPeriods')}</h2><span>{periodRows.length} {t('accounting.periodsConfigured')}</span></div></div>{!isLoading && !periodRows.length && <div className="accounting-state compact"><CalendarDays size={20} /><strong>{t('accounting.noPeriods')}</strong><span>{t('accounting.periodDetail')}</span></div>}{!isLoading && periodRows.length > 0 && <div className="accounting-period-list">{periodRows.slice(0, 8).map((period) => <div className="accounting-period-row" key={period.id}><span><strong>{period.name}</strong><small>{period.starts_on} — {period.ends_on}</small></span><span className={`accounting-status ${period.state}`}>{period.state}</span></div>)}</div>}</article>
      </div>
    </>}
  </section>;
}
