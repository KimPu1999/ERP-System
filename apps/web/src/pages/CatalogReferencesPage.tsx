import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BookOpen, CheckCircle2, Plus, RefreshCw, Search } from 'lucide-react';
import { ApiError, createCatalogReference, getCatalogReferences, type CatalogReference } from '../lib/api';

type ReferenceType = 'unit' | 'brand' | 'category';
const labels: Record<ReferenceType, string> = { unit: 'Units of measure', brand: 'Brands', category: 'Categories' };

export function CatalogReferencesPage() {
  const queryClient = useQueryClient();
  const references = useQuery({ queryKey: ['catalog-references'], queryFn: getCatalogReferences });
  const [active, setActive] = useState<ReferenceType>('unit');
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({ name: '', code: '', decimal_places: '3' });
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const create = useMutation({ mutationFn: () => createCatalogReference({ type: active, name: form.name.trim(), ...(active === 'unit' ? { code: form.code.trim(), decimal_places: Number(form.decimal_places) } : {}) }), onSuccess: () => { setForm({ name: '', code: '', decimal_places: '3' }); setMessage(`${labels[active]} saved.`); setError(''); void queryClient.invalidateQueries({ queryKey: ['catalog-references'] }); }, onError: (reason) => { setMessage(''); setError(reason instanceof ApiError ? reason.message : 'Unable to save this reference.'); } });
  const rows = useMemo(() => { const key = active === 'brand' ? 'brands' : active === 'category' ? 'categories' : 'units'; const source = references.data?.[key] ?? []; const term = search.trim().toLowerCase(); return term ? source.filter((item: CatalogReference) => `${item.name} ${item.code ?? ''}`.toLowerCase().includes(term)) : source; }, [active, references.data, search]);
  const submit = (event: React.FormEvent) => { event.preventDefault(); setMessage(''); setError(''); if (!form.name.trim() || (active === 'unit' && !form.code.trim())) { setError(active === 'unit' ? 'Name and code are required.' : 'Name is required.'); return; } create.mutate(); };
  return <main className="catalog-reference-page">
    <header className="catalog-reference-header"><div><h1>Catalog References</h1><p>Manage the shared values used by Products.</p></div><button className="icon-button" type="button" onClick={() => void references.refetch()} disabled={references.isFetching} aria-label="Refresh"><RefreshCw size={16} className={references.isFetching ? 'is-spinning' : ''} /></button></header>
    <div className="catalog-reference-tabs" role="tablist">{(Object.keys(labels) as ReferenceType[]).map((type) => <button key={type} type="button" role="tab" aria-selected={active === type} className={active === type ? 'active' : ''} onClick={() => { setActive(type); setSearch(''); setMessage(''); setError(''); }}>{labels[type]}</button>)}</div>
    <section className="catalog-reference-grid">
      <form className="catalog-reference-form" onSubmit={submit}><div className="catalog-reference-section-title"><BookOpen size={17} /><strong>Add {labels[active].slice(0, -1)}</strong></div><label>Name<input value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder={active === 'unit' ? 'Piece' : active === 'brand' ? 'Acme' : 'Electronics'} /></label>{active === 'unit' && <><label>Code<input value={form.code} onChange={(event) => setForm((current) => ({ ...current, code: event.target.value }))} placeholder="pcs" /></label><label>Decimal places<input type="number" min="0" max="6" value={form.decimal_places} onChange={(event) => setForm((current) => ({ ...current, decimal_places: event.target.value }))} /></label></>}<button className="primary" type="submit" disabled={create.isPending}><Plus size={15} />{create.isPending ? 'Saving…' : 'Save'}</button>{message && <span className="catalog-reference-success"><CheckCircle2 size={15} />{message}</span>}{error && <span className="catalog-reference-error" role="alert">{error}</span>}</form>
      <section className="catalog-reference-list"><div className="catalog-reference-list-header"><div><h2>{labels[active]}</h2><span>{rows.length} available</span></div><label className="catalog-reference-search"><Search size={15} /><span className="sr-only">Search</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={`Search ${labels[active].toLowerCase()}…`} /></label></div>{references.isLoading && <div className="catalog-reference-state">Loading…</div>}{references.isError && <div className="catalog-reference-state catalog-reference-error">Unable to load catalog references. <button className="secondary" type="button" onClick={() => void references.refetch()}>Retry</button></div>}{!references.isLoading && !references.isError && rows.length === 0 && <div className="catalog-reference-state">No {labels[active].toLowerCase()} available.</div>}{rows.length > 0 && <div className="catalog-reference-items">{rows.map((item: CatalogReference) => <div className="catalog-reference-item" key={item.id}><div><strong>{item.name}</strong><small>{item.code ?? item.slug ?? '—'}</small></div>{active === 'unit' && <span>{item.decimal_places ?? 3} decimals</span>}</div>)}</div>}</section>
    </section>
  </main>;
}
