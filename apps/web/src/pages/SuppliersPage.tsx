import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { ArrowLeft, Plus, RefreshCw, Search, X } from 'lucide-react';
import { activateSupplier, ApiError, createSupplier, deactivateSupplier, getCompanies, getSupplier, getSuppliers, type Company } from '../lib/api';

const errorMessage = (error: unknown): string => error instanceof Error ? error.message : 'Request failed. Please try again.';
const fieldError = (error: unknown, field: string): string | undefined => error instanceof ApiError ? error.errors[field]?.[0] : undefined;

export function SuppliersPage() {
  const [params, setParams] = useSearchParams();
  const id = params.get('supplier_id');
  return id
    ? <Profile id={id} back={() => { const next = new URLSearchParams(params); next.delete('supplier_id'); setParams(next); }} />
    : <SupplierDirectory params={params} setParams={setParams} />;
}

function SupplierDirectory({ params, setParams }: { params: URLSearchParams; setParams: (params: URLSearchParams, options?: { replace?: boolean }) => void }) {
  const requestedCompanyId = params.get('company_id') ?? '';
  const [search, setSearch] = useState(params.get('search') ?? '');
  const [status, setStatus] = useState(params.get('status') ?? '');
  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [type, setType] = useState('business');
  const [success, setSuccess] = useState('');
  const queryClient = useQueryClient();
  const companies = useQuery({ queryKey: ['supplier-companies'], queryFn: getCompanies });
  const companyRows = useMemo<Company[]>(() => (companies.data?.data.data ?? []).filter(company => company.is_active), [companies.data]);
  const companyId = requestedCompanyId || (companyRows.length === 1 ? companyRows[0].id : '');

  useEffect(() => {
    if (!requestedCompanyId && companyRows.length === 1) {
      const next = new URLSearchParams(params);
      next.set('company_id', companyRows[0].id);
      setParams(next, { replace: true });
    }
  }, [companyRows, params, requestedCompanyId, setParams]);

  const query = useMemo(() => ({ company_id: companyId || undefined, search: search.trim() || undefined, status: status || undefined, per_page: 25 }), [companyId, search, status]);
  const list = useQuery({ queryKey: ['suppliers', 'directory', query], queryFn: () => getSuppliers(query), enabled: Boolean(companyId) });
  const create = useMutation({
    mutationFn: () => createSupplier({ company_id: companyId, name: name.trim(), email: email.trim() || null, supplier_type: type }),
    onSuccess: (response) => {
      setOpen(false); setName(''); setEmail(''); setType('business'); setSuccess(`${response.data.name} was added successfully.`);
      const next = new URLSearchParams(params); next.delete('search'); next.delete('status'); next.delete('page'); setParams(next, { replace: true });
      setSearch(''); setStatus('');
      void queryClient.invalidateQueries({ queryKey: ['suppliers'] });
    },
  });
  const change = useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) => active ? activateSupplier(id) : deactivateSupplier(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['suppliers'] }),
  });
  const summary = list.data?.summary;
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); if (!companyId || !name.trim() || create.isPending) return; create.mutate(); };
  const clearFilters = () => { setSearch(''); setStatus(''); const next = new URLSearchParams(params); next.delete('search'); next.delete('status'); next.delete('page'); setParams(next, { replace: true }); };

  return <section className="purchasing-page page-section">
    <header className="purchasing-page-header">
      <div><div className="breadcrumb-line"><span>Purchasing</span><span>/</span><span>Suppliers</span></div><p className="eyebrow">Supplier master data</p><h1>Supplier management</h1></div>
      <div className="purchasing-page-actions"><button className="secondary" onClick={() => void list.refetch()} disabled={list.isFetching}><RefreshCw size={15} className={list.isFetching ? 'spin' : undefined} /> Refresh</button><button className="primary" disabled={!companyId || companies.isLoading} onClick={() => { setSuccess(''); create.reset(); setOpen(true); }}><Plus size={15} /> Add supplier</button></div>
    </header>
    {companies.isError && <div className="inline-error"><span>{errorMessage(companies.error)}</span><button className="secondary" onClick={() => void companies.refetch()}>Retry</button></div>}
    {success && <div className="inline-success"><span>{success}</span><button aria-label="Dismiss success message" onClick={() => setSuccess('')}><X size={15} /></button></div>}
    {!companies.isLoading && companyRows.length > 1 && <div className="supplier-context-card"><label>Company<span className="required-mark">*</span><select value={companyId} onChange={event => { const next = new URLSearchParams(params); next.set('company_id', event.target.value); next.delete('supplier_id'); setParams(next, { replace: true }); }}><option value="">Select an authorized company</option>{companyRows.map(company => <option key={company.id} value={company.id}>{company.name} ({company.code})</option>)}</select></label></div>}
    <div className="crm-grid">{[['Total suppliers', summary?.total], ['Active suppliers', summary?.active], ['Inactive suppliers', summary?.inactive], ['Added in 30 days', summary?.recently_added]].map(([label, value]) => <article className="crm-card" key={label}><span className="muted">{label}</span><strong>{companyId ? (value ?? '—') : '—'}</strong></article>)}</div>
    {open && <form className="card purchase-form-card supplier-create-form" onSubmit={submit} noValidate><div className="form-section-heading"><div><h2>New supplier</h2><p className="muted">Add the supplier to the selected company.</p></div><button type="button" className="icon-button" aria-label="Close form" onClick={() => { create.reset(); setOpen(false); }}><X size={17} /></button></div><div className="crm-inline-form"><label>Legal supplier name<span className="required-mark">*</span><input autoFocus required value={name} onChange={event => setName(event.target.value)} aria-invalid={Boolean(fieldError(create.error, 'name'))} />{fieldError(create.error, 'name') && <small className="field-error">{fieldError(create.error, 'name')}</small>}</label><label>Business email <span className="optional-label">Optional</span><input type="email" value={email} onChange={event => setEmail(event.target.value)} aria-invalid={Boolean(fieldError(create.error, 'email'))} />{fieldError(create.error, 'email') && <small className="field-error">{fieldError(create.error, 'email')}</small>}</label><label>Supplier type<span className="required-mark">*</span><select value={type} onChange={event => setType(event.target.value)}><option value="business">Business</option><option value="individual">Individual</option></select></label></div>{create.isError && <p className="error-text">{errorMessage(create.error)}</p>}<div className="form-actions"><button type="submit" className="primary" disabled={!companyId || !name.trim() || create.isPending}>{create.isPending ? 'Saving…' : 'Save supplier'}</button><button type="button" className="secondary" onClick={() => { create.reset(); setOpen(false); }}>Cancel</button></div></form>}
    <div className="purchasing-filter-toolbar supplier-filter-toolbar"><label><span>Search suppliers</span><div className="crm-search"><Search size={15} /><input placeholder="Code, name, email or phone" value={search} onChange={event => setSearch(event.target.value)} /></div></label><label><span>Status</span><select value={status} onChange={event => setStatus(event.target.value)}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label><button className="secondary" disabled={!search && !status} onClick={clearFilters}>Clear filters</button></div>
    {!companyId && !companies.isLoading && <div className="card supplier-empty-state"><strong>Select an authorized company</strong><p className="muted">Choose a company before viewing or creating suppliers.</p></div>}
    {companyId && <article className="purchasing-table-card table-card">{list.isError ? <div className="supplier-empty-state"><p className="error-text">{errorMessage(list.error)}</p><button className="secondary" onClick={() => void list.refetch()}>Retry</button></div> : <div className="table-scroll"><table><thead><tr><th>Code</th><th>Supplier</th><th>Type / Category</th><th>Primary contact</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>{list.isLoading ? <tr><td colSpan={8}>Loading suppliers…</td></tr> : (list.data?.data.data ?? []).map(supplier => <tr key={supplier.id}><td><strong>{supplier.code}</strong></td><td><Link to={`/purchasing/suppliers?company_id=${companyId}&supplier_id=${supplier.id}`}>{supplier.trading_name ?? supplier.name}</Link></td><td>{supplier.supplier_type ?? '—'} · {supplier.category ?? 'Uncategorized'}</td><td>{supplier.contacts?.find(contact => contact.is_primary)?.name ?? '—'}</td><td>{supplier.email ?? '—'}</td><td>{supplier.phone ?? '—'}</td><td><span className="status-badge">{supplier.status}</span></td><td>{supplier.status === 'active' ? <button className="table-action" disabled={change.isPending} onClick={() => change.mutate({ id: supplier.id, active: false })}>Deactivate</button> : <button className="table-action" disabled={change.isPending} onClick={() => change.mutate({ id: supplier.id, active: true })}>Activate</button>}</td></tr>)}</tbody></table></div>}{list.data && !list.data.data.data.length && <div className="crm-empty">No suppliers for the selected filters.</div>}</article>}
  </section>;
}

function Profile({ id, back }: { id: string; back: () => void }) {
  const query = useQuery({ queryKey: ['supplier', id], queryFn: () => getSupplier(id) });
  if (query.isLoading) return <section className="purchasing-page page-section"><div className="card">Loading supplier profile…</div></section>;
  if (query.isError || !query.data) return <section className="purchasing-page page-section"><p className="error-text">Unable to load supplier profile. <button className="secondary" onClick={() => void query.refetch()}>Retry</button></p></section>;
  const supplier = query.data.data;
  return <section className="purchasing-page page-section"><header className="purchasing-detail-header"><div><button className="text-link" onClick={back}><ArrowLeft size={14} /> Suppliers</button><p className="eyebrow">Supplier profile</p><h1>{supplier.trading_name ?? supplier.name}</h1><p className="muted">{supplier.code} · {supplier.supplier_type ?? 'Business'} · {supplier.category ?? 'Uncategorized'}</p></div><span className="status-badge">{supplier.status}</span></header><div className="purchase-detail-grid"><article className="card"><h2>Overview</h2><p>{supplier.name}</p><p>{supplier.email ?? 'No email'} · {supplier.phone ?? 'No phone'}</p><p>{supplier.address ?? 'No address'}</p><h3>Contacts</h3>{supplier.contacts?.length ? supplier.contacts.map(contact => <p key={contact.id}><strong>{contact.name}</strong> · {contact.email ?? 'No email'}</p>) : <p className="muted">No contacts recorded.</p>}</article><article className="card"><h2>Supplier documents</h2><p className="muted">Document verification is available when configured for this workspace.</p></article><article className="card"><h2>Addresses</h2>{supplier.addresses?.length ? supplier.addresses.map(address => <p key={address.id}><strong>{address.address_type}</strong> · {address.street_address} · {[address.city, address.country].filter(Boolean).join(', ')}</p>) : <p className="muted">No addresses recorded.</p>}</article></div></section>;
}
