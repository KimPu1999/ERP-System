import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { getFiscalPeriods, getLedgerAccounts } from '../lib/api';

export function AccountingPage() {
  const accounts = useQuery({ queryKey: ['ledger-accounts'], queryFn: getLedgerAccounts });
  const periods = useQuery({ queryKey: ['fiscal-periods'], queryFn: getFiscalPeriods });
  const error = (accounts.error ?? periods.error) as Error | null;
  const loading = accounts.isLoading || periods.isLoading;
  const refresh = () => { void accounts.refetch(); void periods.refetch(); };
  const accountRows = accounts.data?.data.data ?? [];
  const periodRows = periods.data?.data.data ?? [];
  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">Accounting</p><h1>Financial control center</h1><p className="muted">Chart of accounts, fiscal controls and ledger readiness. No financial data is posted automatically.</p></div><button className="icon-button" type="button" aria-label="Refresh accounting" onClick={refresh}><RefreshCw size={16} /></button></div>{error && <div className="alert alert-danger" role="alert">Unable to load accounting setup. <button className="table-action" type="button" onClick={refresh}>Retry</button></div>}<div className="metric-grid"><div className="metric"><span>Ledger accounts</span><strong>{loading ? '…' : accountRows.length}</strong></div><div className="metric"><span>Fiscal periods</span><strong>{loading ? '…' : periodRows.length}</strong></div><div className="metric"><span>Open periods</span><strong>{loading ? '…' : periodRows.filter((period) => period.state === 'open').length}</strong></div></div><div className="panel"><h2>Chart of accounts</h2><p className="muted">Tenant accounting configuration. Posted entries are not changed from this view.</p>{loading && <div className="empty">Loading accounting setup…</div>}{!loading && !error && accountRows.length === 0 && <div className="empty">No accounts configured for this tenant. An authorized administrator must configure the chart of accounts before posting journals.</div>}{!loading && !error && accountRows.length > 0 && <div className="table-scroll"><table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Normal balance</th></tr></thead><tbody>{accountRows.map((account) => <tr key={account.id}><td>{account.code}</td><td>{account.name}</td><td>{account.type}</td><td>{account.normal_balance}</td></tr>)}</tbody></table></div>}</div></section>;
}
