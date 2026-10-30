import { FormEvent, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Plus, RefreshCw, RotateCcw, Search, X } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { ApiError, createProductCategory, getProductCategories } from '../lib/api';

const pageSizes = [10, 25, 50, 100] as const;

export function ProductCategoriesPage() {
  const [params, setParams] = useSearchParams();
  const search = params.get('search') ?? '';
  const sort = params.get('sort') ?? 'name';
  const direction = params.get('direction') === 'desc' ? 'desc' as const : 'asc' as const;
  const page = Number(params.get('page') ?? 1);
  const perPage = pageSizes.includes(Number(params.get('per_page')) as (typeof pageSizes)[number]) ? Number(params.get('per_page')) as (typeof pageSizes)[number] : 25;
  const query = { search: search || undefined, page, per_page: perPage, sort, direction };
  const categories = useQuery({ queryKey: ['product-categories', 'catalog', query], queryFn: () => getProductCategories(query) });
  const queryClient = useQueryClient();
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState({ name: '', slug: '', parent_id: '' });
  const [success, setSuccess] = useState('');
  const parentOptions = useQuery({ queryKey: ['product-categories', 'create-options'], queryFn: () => getProductCategories({ per_page: 100, sort: 'name', direction: 'asc' }), enabled: showCreate });
  const create = useMutation({ mutationFn: () => createProductCategory({ name: form.name.trim(), slug: form.slug.trim() || undefined, parent_id: form.parent_id || null }), onSuccess: (response) => { setShowCreate(false); setForm({ name: '', slug: '', parent_id: '' }); setSuccess(`${response.data.name} was added successfully.`); void queryClient.invalidateQueries({ queryKey: ['product-categories'] }); } });
  const fieldError = (field: string) => create.error instanceof ApiError ? create.error.errors[field]?.[0] : undefined;
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); if (!form.name.trim() || create.isPending) return; create.mutate(); };
  const result = categories.data?.data;

  const update = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value); else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next, { replace: true });
  };
  const clear = () => setParams(new URLSearchParams(), { replace: true });

  return <section className="page-section product-catalog-page product-categories-page">
    <header className="product-catalog-header"><div className="product-catalog-title"><Link className="product-catalog-back" to="/catalog" aria-label="Back to product catalog"><ArrowLeft size={17} /></Link><h1>Product Categories</h1></div><div className="product-catalog-actions"><button className="icon-button" type="button" aria-label="Refresh categories" onClick={() => void categories.refetch()} disabled={categories.isFetching}><RefreshCw size={16} className={categories.isFetching ? 'product-catalog-spin' : undefined} /></button><button className="primary" type="button" onClick={() => { create.reset(); setShowCreate(true); }}><Plus size={15} /> Add category</button></div></header>
    {success && <div className="inline-success"><span>{success}</span><button type="button" aria-label="Dismiss success message" onClick={() => setSuccess('')}><X size={15} /></button></div>}
    {showCreate && <form className="card product-create-form" onSubmit={submit} noValidate><div className="product-create-heading"><div><h2>Add category</h2><p>Create a root category or place it under an existing category.</p></div><button className="icon-button" type="button" aria-label="Close category form" onClick={() => { create.reset(); setShowCreate(false); }}><X size={17} /></button></div><div className="product-create-grid"><label>Category name <b className="required-mark">*</b><input autoFocus required value={form.name} onChange={event => setForm({ ...form, name: event.target.value })} aria-invalid={Boolean(fieldError('name'))} />{fieldError('name') && <small className="field-error">{fieldError('name')}</small>}</label><label>Category slug <span className="optional-label">Optional</span><input placeholder="Generated from name" value={form.slug} onChange={event => setForm({ ...form, slug: event.target.value })} aria-invalid={Boolean(fieldError('slug'))} />{fieldError('slug') && <small className="field-error">{fieldError('slug')}</small>}</label><label>Parent category <span className="optional-label">Optional</span><select value={form.parent_id} onChange={event => setForm({ ...form, parent_id: event.target.value })}><option value="">Root category</option>{(parentOptions.data?.data.data ?? []).map(category => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label></div>{parentOptions.isError && <p className="error-text">Unable to load parent categories. Retry before saving a subcategory.</p>}{create.isError && <p className="error-text">{create.error instanceof Error ? create.error.message : 'Unable to save category.'}</p>}<div className="product-create-actions"><button className="secondary" type="button" onClick={() => { create.reset(); setShowCreate(false); }}>Cancel</button><button className="primary" type="submit" disabled={create.isPending || !form.name.trim()}>{create.isPending ? 'Saving…' : 'Save category'}</button></div></form>}
    <div className="product-catalog-filter-toolbar product-category-toolbar"><label className="product-catalog-search"><Search size={15} /><span className="sr-only">Search categories</span><input aria-label="Search categories" placeholder="Search category name or slug" value={search} onChange={(event) => update('search', event.target.value)} /></label><label><span>Sort</span><select value={sort + ':' + direction} onChange={(event) => { const [nextSort, nextDirection] = event.target.value.split(':'); const next = new URLSearchParams(params); next.set('sort', nextSort); next.set('direction', nextDirection); next.delete('page'); setParams(next, { replace: true }); }}><option value="name:asc">Name A–Z</option><option value="name:desc">Name Z–A</option><option value="updated_at:desc">Recently updated</option><option value="created_at:desc">Recently created</option></select></label><div className="product-catalog-filter-actions"><button className="secondary" type="button" disabled={!search && sort === 'name' && direction === 'asc' && !params.get('per_page')} onClick={clear}><RotateCcw size={14} /> Clear</button><label className="product-catalog-rows"><span>Rows</span><select aria-label="Rows per page" value={perPage} onChange={(event) => update('per_page', event.target.value)}>{pageSizes.map((size) => <option value={size} key={size}>{size}</option>)}</select></label></div></div>
    {categories.isError && <div className="alert alert-danger product-catalog-alert" role="alert">Unable to load categories. <button className="text-link" type="button" onClick={() => void categories.refetch()}>Retry</button></div>}
    <article className="table-card product-catalog-table-card"><div className="product-catalog-table-heading"><div><h2>Categories</h2><span>{result ? result.total + ' categor' + (result.total === 1 ? 'y' : 'ies') : categories.isLoading ? 'Loading…' : 'No records'}</span></div></div><div className="table-scroll"><table><thead><tr><th>Category</th><th>Slug</th><th>Parent</th><th>Products</th><th>Subcategories</th><th>Updated</th></tr></thead><tbody>
      {categories.isLoading ? <tr><td colSpan={6}><div className="product-catalog-loading"><span /><span /><span /></div></td></tr> : (result?.data ?? []).map((category) => <tr key={category.id}><td><strong>{category.name}</strong></td><td><span className="product-catalog-secondary-value">{category.slug}</span></td><td>{category.parent?.name ?? 'Root category'}</td><td>{category.direct_product_count}</td><td>{category.subcategory_count}</td><td>{new Date(category.updated_at).toLocaleDateString()}</td></tr>)}
      {!categories.isLoading && !categories.isError && !result?.data.length && <tr><td colSpan={6}><div className="product-catalog-empty"><strong>No product categories found</strong><span>{search ? 'Try another search term.' : 'Categories will appear here when they are configured.'}</span></div></td></tr>}
    </tbody></table></div>{result && <footer className="product-catalog-pagination"><span>Page {result.current_page} of {result.last_page}</span><div><button className="secondary" type="button" disabled={result.current_page <= 1 || categories.isFetching} onClick={() => update('page', String(result.current_page - 1))}>Previous</button><button className="secondary" type="button" disabled={result.current_page >= result.last_page || categories.isFetching} onClick={() => update('page', String(result.current_page + 1))}>Next</button></div></footer>}</article>
  </section>;
}
