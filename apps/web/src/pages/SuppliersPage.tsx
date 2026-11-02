import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { ArrowLeft, FileText, MoreHorizontal, Pencil, Plus, RefreshCw, Search, X } from 'lucide-react';
import { activateSupplier, ApiError, createSupplier, deactivateSupplier, getCompanies, getSupplier, getSupplierDocuments, getSuppliers, updateSupplier, type Company } from '../lib/api';

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
  const [phone, setPhone] = useState('');
  const [type, setType] = useState('business');
  const [success, setSuccess] = useState('');
  const nameInputRef = useRef<HTMLInputElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
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
    mutationFn: () => createSupplier({ company_id: companyId, name: name.trim(), email: email.trim() || null, phone: phone.trim() || null, supplier_type: type }),
    onSuccess: (response) => {
      setOpen(false); setName(''); setEmail(''); setPhone(''); setType('business'); setSuccess(`${response.data.name} was added successfully.`);
      const next = new URLSearchParams(params); next.delete('search'); next.delete('status'); next.delete('page'); setParams(next, { replace: true });
      setSearch(''); setStatus('');
      void queryClient.invalidateQueries({ queryKey: ['suppliers'] });
    },
  });
  useEffect(() => {
    if (!open) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    nameInputRef.current?.focus();
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        create.reset();
        setOpen(false);
        return;
      }
      if (event.key !== 'Tab' || !dialogRef.current) return;
      const focusable = dialogRef.current.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled])');
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => { document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', onKeyDown); };
  }, [open, create]);
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
    {open && <div className="supplier-modal-backdrop" role="presentation" onMouseDown={event => { if (event.target === event.currentTarget) { create.reset(); setOpen(false); } }}><div className="supplier-modal" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="new-supplier-title" aria-describedby="new-supplier-description"><form onSubmit={submit} noValidate><header className="supplier-modal-header"><div><p className="eyebrow">Supplier master data</p><h2 id="new-supplier-title">New supplier</h2><p id="new-supplier-description" className="muted">Add a supplier to the selected company.</p></div><button type="button" className="icon-button" aria-label="Close new supplier dialog" onClick={() => { create.reset(); setOpen(false); }}><X size={17} /></button></header><div className="supplier-modal-body"><div className="supplier-form-grid"><label htmlFor="supplier-name">Legal supplier name <span className="required-mark" aria-hidden="true">*</span><span className="sr-only">required</span><input id="supplier-name" ref={nameInputRef} required value={name} onChange={event => setName(event.target.value)} placeholder="e.g. Golden Star Trading Co., Ltd." aria-invalid={Boolean(fieldError(create.error, 'name'))} aria-describedby={fieldError(create.error, 'name') ? 'supplier-name-error' : undefined} />{fieldError(create.error, 'name') && <small id="supplier-name-error" className="field-error" role="alert">{fieldError(create.error, 'name')}</small>}</label><label htmlFor="supplier-type">Supplier type <span className="required-mark" aria-hidden="true">*</span><span className="sr-only">required</span><select id="supplier-type" required value={type} onChange={event => setType(event.target.value)}><option value="business">Business</option><option value="individual">Individual</option></select></label><label htmlFor="supplier-email">Business email <span className="optional-label">Optional</span><input id="supplier-email" type="email" value={email} onChange={event => setEmail(event.target.value)} placeholder="name@company.com" aria-invalid={Boolean(fieldError(create.error, 'email'))} aria-describedby={fieldError(create.error, 'email') ? 'supplier-email-error' : undefined} />{fieldError(create.error, 'email') && <small id="supplier-email-error" className="field-error" role="alert">{fieldError(create.error, 'email')}</small>}</label><label htmlFor="supplier-phone">Phone number <span className="optional-label">Optional</span><input id="supplier-phone" type="tel" value={phone} onChange={event => setPhone(event.target.value)} placeholder="e.g. +95 9 000 000 000" aria-invalid={Boolean(fieldError(create.error, 'phone'))} aria-describedby={fieldError(create.error, 'phone') ? 'supplier-phone-error' : undefined} />{fieldError(create.error, 'phone') && <small id="supplier-phone-error" className="field-error" role="alert">{fieldError(create.error, 'phone')}</small>}</label></div>{create.isError && <p className="supplier-form-error" role="alert">{errorMessage(create.error)}</p>}</div><footer className="supplier-modal-footer"><button type="button" className="secondary" onClick={() => { create.reset(); setOpen(false); }}>Cancel</button><button type="submit" className="primary" disabled={!companyId || !name.trim() || create.isPending}>{create.isPending ? 'Saving…' : 'Save supplier'}</button></footer></form></div></div>}
    <div className="purchasing-filter-toolbar supplier-filter-toolbar"><label><span>Search suppliers</span><div className="crm-search"><Search size={15} /><input placeholder="Code, name, email or phone" value={search} onChange={event => setSearch(event.target.value)} /></div></label><label><span>Status</span><select value={status} onChange={event => setStatus(event.target.value)}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label><button className="secondary" disabled={!search && !status} onClick={clearFilters}>Clear filters</button></div>
    {!companyId && !companies.isLoading && <div className="card supplier-empty-state"><strong>Select an authorized company</strong><p className="muted">Choose a company before viewing or creating suppliers.</p></div>}
    {companyId && <article className="purchasing-table-card table-card">{list.isError ? <div className="supplier-empty-state"><p className="error-text">{errorMessage(list.error)}</p><button className="secondary" onClick={() => void list.refetch()}>Retry</button></div> : <div className="table-scroll"><table><thead><tr><th>Code</th><th>Supplier</th><th>Type / Category</th><th>Primary contact</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>{list.isLoading ? <tr><td colSpan={8}>Loading suppliers…</td></tr> : (list.data?.data.data ?? []).map(supplier => <tr key={supplier.id}><td><strong>{supplier.code}</strong></td><td><Link to={`/purchasing/suppliers?company_id=${companyId}&supplier_id=${supplier.id}`}>{supplier.trading_name ?? supplier.name}</Link></td><td>{supplier.supplier_type ?? '—'} · {supplier.category ?? 'Uncategorized'}</td><td>{supplier.contacts?.find(contact => contact.is_primary)?.name ?? '—'}</td><td>{supplier.email ?? '—'}</td><td>{supplier.phone ?? '—'}</td><td><span className="status-badge">{supplier.status}</span></td><td>{supplier.status === 'active' ? <button className="table-action" disabled={change.isPending} onClick={() => change.mutate({ id: supplier.id, active: false })}>Deactivate</button> : <button className="table-action" disabled={change.isPending} onClick={() => change.mutate({ id: supplier.id, active: true })}>Activate</button>}</td></tr>)}</tbody></table></div>}{list.data && !list.data.data.data.length && <div className="crm-empty">No suppliers for the selected filters.</div>}</article>}
  </section>;
}

function Profile({ id, back }: { id: string; back: () => void }) {
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const query = useQuery({ queryKey: ['supplier', id], queryFn: () => getSupplier(id) });
  const documents = useQuery({ queryKey: ['supplier-documents', id], queryFn: () => getSupplierDocuments(id), enabled: Boolean(id) });
  const save = useMutation({ mutationFn: () => updateSupplier(id, { name: form.name.trim(), trading_name: form.trading_name.trim() || null, supplier_type: form.supplier_type, category: form.category.trim() || null, email: form.email.trim() || null, phone: form.phone.trim() || null, address: form.address.trim() || null, status: form.status }), onSuccess: () => { setEditing(false); void queryClient.invalidateQueries({ queryKey: ['supplier', id] }); } });
  if (query.isLoading) return <section className="purchasing-page page-section"><div className="supplier-detail-state card">Loading supplier profile…</div></section>;
  if (query.isError || !query.data) return <section className="purchasing-page page-section"><p className="error-text">Unable to load supplier profile. <button className="secondary" onClick={() => void query.refetch()}>Retry</button></p></section>;
  const supplier = query.data.data;
  const openEdit = () => { save.reset(); setForm({ name: supplier.name, trading_name: supplier.trading_name ?? '', supplier_type: supplier.supplier_type ?? 'business', category: supplier.category ?? '', email: supplier.email ?? '', phone: supplier.phone ?? '', address: supplier.address ?? '', status: supplier.status }); setEditing(true); };
  return <section className="purchasing-page page-section supplier-detail-page"><header className="supplier-detail-header card"><button className="supplier-detail-back text-link" type="button" onClick={back}><ArrowLeft size={14} /> Suppliers</button><span className="supplier-detail-avatar" aria-hidden="true">{(supplier.trading_name ?? supplier.name).slice(0, 1).toUpperCase()}</span><div className="supplier-detail-identity"><div className="supplier-detail-title"><h1>{supplier.trading_name ?? supplier.name}</h1><span className={`status-badge ${supplier.status}`}>{supplier.status}</span></div><p>{supplier.code} · {supplier.supplier_type ?? 'Business'} · {supplier.category ?? 'Uncategorized'}</p></div><div className="supplier-detail-actions"><button className="primary" type="button" onClick={openEdit}><Pencil size={14} /> Edit supplier</button><details><summary className="icon-action" aria-label="More supplier actions"><MoreHorizontal size={17} /></summary><div className="supplier-action-menu"><span>Supplier actions are limited to authorized workflows.</span></div></details></div></header><div className="supplier-detail-grid"><article className="card supplier-detail-card supplier-overview-card"><div className="supplier-detail-card-heading"><div><p className="eyebrow">Supplier profile</p><h2>Overview</h2></div></div><dl className="supplier-info-grid"><div><dt>Supplier name</dt><dd>{supplier.name || '—'}</dd></div><div><dt>Supplier code</dt><dd>{supplier.code || '—'}</dd></div><div><dt>Supplier type</dt><dd>{supplier.supplier_type ?? 'Business'}</dd></div><div><dt>Category</dt><dd>{supplier.category ?? 'Uncategorized'}</dd></div><div><dt>Email</dt><dd>{supplier.email ?? '—'}</dd></div><div><dt>Phone</dt><dd>{supplier.phone ?? '—'}</dd></div><div><dt>Status</dt><dd>{supplier.status || '—'}</dd></div><div className="supplier-info-span"><dt>Address</dt><dd>{supplier.address ?? (supplier.city || '—')}</dd></div></dl></article><article className="card supplier-detail-card supplier-documents-card"><div className="supplier-detail-card-heading"><div><p className="eyebrow">Compliance</p><h2><FileText size={15} /> Supplier documents</h2></div></div>{documents.isLoading ? <p className="muted">Loading documents…</p> : documents.isError ? <p className="error-text">Unable to load supplier documents.</p> : documents.data?.data.data.length ? <div className="supplier-document-list">{documents.data.data.data.map((document) => <div className="supplier-document-row" key={document.id}><div><strong>{document.document_type}</strong><small>{document.original_filename} · Version {document.version}</small></div><span className={`status-badge ${document.status}`}>{document.status}</span></div>)}</div> : <p className="supplier-empty-copy">No supplier documents are available for this company.</p>}</article><article className="card supplier-detail-card"><div className="supplier-detail-card-heading"><div><p className="eyebrow">Contacts</p><h2>Supplier contacts</h2></div></div>{supplier.contacts?.length ? <div className="supplier-contact-list">{supplier.contacts.map((contact) => <div className="supplier-contact-row" key={contact.id}><div><strong>{contact.name}</strong><small>{contact.email ?? contact.phone ?? 'No contact details'}</small></div>{contact.is_primary && <span className="supplier-primary-badge">Primary</span>}</div>)}</div> : <p className="supplier-empty-copy">No contacts recorded.</p>}</article><article className="card supplier-detail-card"><div className="supplier-detail-card-heading"><div><p className="eyebrow">Addresses</p><h2>Supplier addresses</h2></div></div>{supplier.addresses?.length ? <div className="supplier-address-list">{supplier.addresses.map((address) => <div className="supplier-address-row" key={address.id}><div><strong>{address.address_type.replaceAll('_', ' ')}</strong><small>{address.street_address}</small><span>{[address.city, address.country].filter(Boolean).join(', ') || '—'}</span></div>{address.is_default && <span className="supplier-primary-badge">Default</span>}</div>)}</div> : <p className="supplier-empty-copy">No addresses recorded.</p>}</article></div>{editing && <div className="supplier-edit-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setEditing(false); }}><div className="supplier-edit-modal" role="dialog" aria-modal="true" aria-labelledby="supplier-edit-title"><form onSubmit={(event) => { event.preventDefault(); if (form.name.trim() && !save.isPending) save.mutate(); }}><header><div><p className="eyebrow">Supplier profile</p><h2 id="supplier-edit-title">Edit supplier</h2></div><button className="icon-action" type="button" aria-label="Close edit supplier dialog" onClick={() => setEditing(false)}><X size={17} /></button></header><div className="supplier-edit-grid"><label>Legal supplier name<input required value={form.name ?? ''} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label><label>Trading name<input value={form.trading_name ?? ''} onChange={(event) => setForm({ ...form, trading_name: event.target.value })} /></label><label>Supplier type<select value={form.supplier_type ?? 'business'} onChange={(event) => setForm({ ...form, supplier_type: event.target.value })}><option value="business">Business</option><option value="individual">Individual</option></select></label><label>Category<input value={form.category ?? ''} onChange={(event) => setForm({ ...form, category: event.target.value })} /></label><label>Email<input type="email" value={form.email ?? ''} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label><label>Phone<input type="tel" value={form.phone ?? ''} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label><label className="supplier-edit-span">Address<textarea rows={3} value={form.address ?? ''} onChange={(event) => setForm({ ...form, address: event.target.value })} /></label><label>Status<select value={form.status ?? 'active'} onChange={(event) => setForm({ ...form, status: event.target.value })}><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label></div>{save.isError && <p className="supplier-edit-error" role="alert">{errorMessage(save.error)}</p>}<footer><button className="secondary" type="button" onClick={() => setEditing(false)}>Cancel</button><button className="primary" type="submit" disabled={save.isPending || !form.name?.trim()}>{save.isPending ? 'Saving…' : 'Save changes'}</button></footer></form></div></div>}</section>;
}
