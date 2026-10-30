import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, RefreshCw, Search } from 'lucide-react';
import { createSupplierOnboarding, getSupplierOnboarding, getSuppliers, type SupplierOnboarding } from '../lib/api';
import { useSearchParams } from 'react-router-dom';

const errorMessage = (error: unknown) => error instanceof Error ? error.message : 'Request failed';

export function SupplierOnboardingPage() {
  const [params] = useSearchParams();
  const companyId = params.get('company_id') ?? undefined;
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(false);
  const [supplierId, setSupplierId] = useState('');
  const query = { company_id: companyId, search: search || undefined, per_page: 25 };
  const list = useQuery({ queryKey: ['supplier-onboarding', query], queryFn: () => getSupplierOnboarding(query) });
  const suppliers = useQuery({ queryKey: ['supplier-onboarding-suppliers', companyId], queryFn: () => getSuppliers({ company_id: companyId, per_page: 100 }), enabled: open && Boolean(companyId) });
  const qc = useQueryClient();
  const create = useMutation({
    mutationFn: () => createSupplierOnboarding(supplierId, { company_id: companyId!, application_type: 'new_supplier_onboarding' }),
    onSuccess: () => { setOpen(false); setSupplierId(''); void qc.invalidateQueries({ queryKey: ['supplier-onboarding'] }); },
  });
  const summary = list.data?.summary;
  const cards: Array<[string, number | undefined]> = [['Total applications', summary?.total], ['Pending review', summary?.pending_review], ['Needs changes', summary?.needs_changes], ['Approved', summary?.approved], ['Rejected', summary?.rejected], ['Expiring documents', summary?.expiring_documents]];
  return <section className="purchasing-page page-section">
    <header className="purchasing-page-header"><div><div className="breadcrumb-line"><span>Purchasing</span><span>/</span><span>Suppliers</span><span>/</span><span>Onboarding</span></div><p className="eyebrow">Supplier due diligence</p><h1>Supplier onboarding</h1><p className="muted">Localization & financial preferences</p></div><div className="purchasing-page-actions"><button className="secondary" onClick={() => void list.refetch()}><RefreshCw size={15} /> Refresh</button><button className="primary" disabled={!companyId} onClick={() => setOpen(true)}><Plus size={15} /> New application</button></div></header>
    <div className="crm-grid">{cards.map(([label, value]) => <article className="crm-card" key={label}><span className="muted">{label}</span><strong>{value ?? '—'}</strong></article>)}</div>
    {open && <article className="card purchase-form-card"><h2>Start onboarding application</h2><p className="muted">This links to an existing supplier. It does not create a duplicate supplier record.</p><label>Supplier<select value={supplierId} onChange={event => setSupplierId(event.target.value)}><option value="">Select supplier</option>{(suppliers.data?.data.data ?? []).filter(s => s.status !== 'archived').map(s => <option key={s.id} value={s.id}>{s.code} · {s.trading_name ?? s.name}</option>)}</select></label><div className="form-actions"><button className="primary" disabled={!supplierId || create.isPending} onClick={() => create.mutate()}>Create draft</button><button className="secondary" onClick={() => setOpen(false)}>Cancel</button></div>{create.isError && <p className="error-text">{errorMessage(create.error)}</p>}</article>}
    <div className="purchasing-filter-toolbar"><label><span>Search</span><div className="crm-search"><Search size={15} /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Application number or supplier" /></div></label></div>
    <article className="purchasing-table-card table-card">{list.isError ? <p className="error-text">{errorMessage(list.error)}</p> : <div className="table-scroll"><table><thead><tr><th>Application</th><th>Supplier</th><th>Type</th><th>Submitted</th><th>Documents</th><th>Review</th><th>Approval</th></tr></thead><tbody>{list.isLoading ? <tr><td colSpan={7}>Loading applications…</td></tr> : (list.data?.data.data ?? []).map((application: SupplierOnboarding) => <tr key={application.id}><td><strong>{application.number}</strong><div className="muted">v{application.version}</div></td><td>{application.supplier?.trading_name ?? application.supplier?.name ?? '—'}</td><td>{application.application_type.replaceAll('_', ' ')}</td><td>{application.submitted_at ? new Date(application.submitted_at).toLocaleDateString() : 'Not submitted'}</td><td>{application.documents?.filter(document => document.status === 'verified').length ?? 0} verified</td><td><span className="status-badge">{application.status}</span></td><td><span className="status-badge">{application.approval_status}</span></td></tr>)}</tbody></table></div>}{list.data && !list.data.data.data.length && <div className="crm-empty">No onboarding applications for the selected company.</div>}</article>
  </section>;
}
