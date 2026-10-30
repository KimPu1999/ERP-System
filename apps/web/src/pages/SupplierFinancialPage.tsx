import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { RefreshCw, Search } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { getSupplierFinancialDirectory, type SupplierFinancialRow } from '../lib/api';

export function SupplierFinancialPage() {
  const [params] = useSearchParams();
  const companyId = params.get('company_id') ?? undefined;
  const [search, setSearch] = useState('');
  const query = { company_id: companyId, search: search || undefined, per_page: 25 };
  const q = useQuery({ queryKey: ['supplier-financial-directory', query], queryFn: () => getSupplierFinancialDirectory(query) });
  const summary = q.data?.summary;
  const cards: Array<[string, number | undefined]> = [['Financial profiles', summary?.financial_profiles], ['Verified bank accounts', summary?.verified_bank_accounts], ['Pending verification', summary?.pending_bank_verification], ['Active payment holds', summary?.active_payment_holds], ['Suppliers with overdue AP', summary?.suppliers_with_overdue_ap], ['Credit exceptions', summary?.credit_exceptions]];
  return <section className="purchasing-page page-section"><header className="purchasing-page-header"><div><div className="breadcrumb-line"><span>Purchasing</span><span>/</span><span>Suppliers</span><span>/</span><span>Financials</span></div><p className="eyebrow">Supplier financial controls</p><h1>Supplier financial management</h1><p className="muted">Review payment terms, beneficiary verification, payment holds and AP exposure.</p></div><button className="secondary" onClick={() => void q.refetch()}><RefreshCw size={15} /> Refresh</button></header><div className="crm-grid">{cards.map(([label, value]) => <article className="crm-card" key={label}><span className="muted">{label}</span><strong>{value ?? '—'}</strong></article>)}</div><div className="purchasing-filter-toolbar"><label><span>Search supplier</span><div className="crm-search"><Search size={15} /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Supplier code or name" /></div></label></div><article className="purchasing-table-card table-card">{q.isError ? <p className="error-text">Unable to load supplier financial data.</p> : <div className="table-scroll"><table><thead><tr><th>Supplier</th><th>Currency</th><th>Payment terms</th><th>Credit facility</th><th>Credit status</th><th>Actions</th></tr></thead><tbody>{q.isLoading ? <tr><td colSpan={6}>Loading financial profiles…</td></tr> : (q.data?.data.data ?? []).map((row: SupplierFinancialRow) => <tr key={row.id}><td><strong>{row.supplier?.trading_name ?? row.supplier?.name ?? '—'}</strong><div className="muted">{row.supplier?.code}</div></td><td>{row.currency?.code ?? '—'}</td><td>{row.payment_terms ?? 'Not configured'}</td><td>{row.credit_limit ?? 'Unavailable'}</td><td><span className="status-badge">{row.credit_hold_status ?? 'none'}</span></td><td><span className="muted">View profile from supplier record</span></td></tr>)}</tbody></table></div>}{q.data && !q.data.data.data.length && <div className="crm-empty">No supplier financial profiles for this company.</div>}</article></section>;
}
