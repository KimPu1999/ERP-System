import { useQuery } from '@tanstack/react-query';
import { getFiscalPeriods, getLedgerAccounts } from '../lib/api';

export function AccountingPage() {
  const accounts = useQuery({ queryKey: ['ledger-accounts'], queryFn: getLedgerAccounts });
  const periods = useQuery({ queryKey: ['fiscal-periods'], queryFn: getFiscalPeriods });
  const error = (accounts.error ?? periods.error) as Error | null;
  return <section><p className="eyebrow">Accounting</p><h1>Financial control center</h1><p className="muted">Chart of accounts, fiscal controls and ledger readiness. No financial data is posted automatically.</p>{error && <div className="empty error">{error.message}</div>}<div className="metric-grid"><div className="metric"><span>Ledger accounts</span><strong>{accounts.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Fiscal periods</span><strong>{periods.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Open periods</span><strong>{periods.data?.data.data.filter((period) => period.state === 'open').length ?? '—'}</strong></div></div><div className="panel"><h2>Chart of accounts</h2><table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Normal balance</th></tr></thead><tbody>{accounts.data?.data.data.map((account) => <tr key={account.id}><td>{account.code}</td><td>{account.name}</td><td>{account.type}</td><td>{account.normal_balance}</td></tr>)}</tbody></table>{accounts.data?.data.data.length === 0 && <div className="empty">No accounts configured for this tenant.</div>}</div></section>;
}
