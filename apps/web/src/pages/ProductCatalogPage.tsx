import { useQuery } from '@tanstack/react-query';
import { Package, RefreshCw, RotateCcw, Search, Tags } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { getProducts } from '../lib/api';

const pageSizes = [10, 25, 50, 100] as const;
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export function ProductCatalogPage() {
  const [params, setParams] = useSearchParams();
  const search = params.get('search') ?? '';
  const status = params.get('status') ?? '';
  const sort = params.get('sort') ?? 'name';
  const direction = params.get('direction') === 'desc' ? 'desc' as const : 'asc' as const;
  const page = Number(params.get('page') ?? 1);
  const perPage = pageSizes.includes(Number(params.get('per_page')) as (typeof pageSizes)[number]) ? Number(params.get('per_page')) as (typeof pageSizes)[number] : 25;
  const query = { search: search || undefined, status: status || undefined, sort, direction, page, per_page: perPage };
  const products = useQuery({ queryKey: ['products', 'catalog', query], queryFn: () => getProducts(query) });
  const result = products.data?.data;
  const rows = result?.data.flatMap((product) => product.variants.map((variant) => ({ product, variant }))) ?? [];

  const update = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value); else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next, { replace: true });
  };
  const clearFilters = () => setParams(new URLSearchParams(), { replace: true });

  return <section className="page-section product-catalog-page">
    <header className="product-catalog-header">
      <div className="product-catalog-title"><span className="product-catalog-icon"><Package size={18} /></span><h1>Product Catalog</h1></div>
      <div className="product-catalog-actions"><Link className="secondary" to="/catalog/categories"><Tags size={15} /> Categories</Link><button className="icon-button" type="button" aria-label="Refresh products" onClick={() => void products.refetch()} disabled={products.isFetching}><RefreshCw size={16} className={products.isFetching ? 'product-catalog-spin' : undefined} /></button></div>
    </header>
    <div className="product-catalog-filter-toolbar">
      <label className="product-catalog-search"><Search size={15} /><span className="sr-only">Search products</span><input aria-label="Search products" placeholder="Search products, SKU or barcode" value={search} onChange={(event) => update('search', event.target.value)} /></label>
      <label><span>Status</span><select value={status} onChange={(event) => update('status', event.target.value)}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
      <label><span>Sort</span><select value={sort + ':' + direction} onChange={(event) => { const [nextSort, nextDirection] = event.target.value.split(':'); const next = new URLSearchParams(params); next.set('sort', nextSort); next.set('direction', nextDirection); next.delete('page'); setParams(next, { replace: true }); }}><option value="name:asc">Name A–Z</option><option value="name:desc">Name Z–A</option><option value="created_at:desc">Newest first</option><option value="status:asc">Status</option></select></label>
      <div className="product-catalog-filter-actions"><button className="secondary" type="button" disabled={!search && !status && sort === 'name' && direction === 'asc' && !params.get('per_page')} onClick={clearFilters}><RotateCcw size={14} /> Clear</button><label className="product-catalog-rows"><span>Rows</span><select aria-label="Rows per page" value={perPage} onChange={(event) => update('per_page', event.target.value)}>{pageSizes.map((size) => <option value={size} key={size}>{size}</option>)}</select></label></div>
    </div>
    {products.isError && <div className="alert alert-danger product-catalog-alert" role="alert">Unable to load products. <button className="text-link" type="button" onClick={() => void products.refetch()}>Retry</button></div>}
    <article className="table-card product-catalog-table-card">
      <div className="product-catalog-table-heading"><div><h2>Products</h2><span>{result ? result.total + ' product' + (result.total === 1 ? '' : 's') : products.isLoading ? 'Loading…' : 'No records'}</span></div><span className="product-catalog-variant-meta">{products.isFetching && !products.isLoading ? 'Refreshing…' : rows.length + ' variants on this page'}</span></div>
      <div className="table-scroll"><table><thead><tr><th>Product</th><th>SKU</th><th>Internal barcode</th><th>Price</th><th>Status</th></tr></thead><tbody>
        {products.isLoading ? <tr><td colSpan={5}><div className="product-catalog-loading"><span /><span /><span /></div></td></tr> : rows.map(({ product, variant }) => <tr key={variant.id}><td><div className="product-catalog-product-cell"><span className="product-catalog-product-icon"><Package size={15} /></span><div><strong>{product.name}</strong><small>Variant record</small></div></div></td><td><span className="product-catalog-secondary-value">{variant.sku}</span></td><td>{variant.internal_barcode || '—'}</td><td className="product-catalog-price">{variant.price}</td><td><span className={'status-badge ' + (product.status === 'active' ? 'active' : 'neutral')}>{label(product.status)}</span></td></tr>)}
        {!products.isLoading && !products.isError && rows.length === 0 && <tr><td colSpan={5}><div className="product-catalog-empty"><span className="product-catalog-empty-icon"><Package size={20} /></span><strong>No products found</strong><span>{search || status ? 'Try clearing the filters or using another search.' : 'Products will appear here when they are available in the catalog.'}</span></div></td></tr>}
      </tbody></table></div>
      {result && <footer className="product-catalog-pagination"><span>Page {result.current_page} of {result.last_page}</span><div><button className="secondary" type="button" disabled={result.current_page <= 1 || products.isFetching} onClick={() => update('page', String(result.current_page - 1))}>Previous</button><button className="secondary" type="button" disabled={result.current_page >= result.last_page || products.isFetching} onClick={() => update('page', String(result.current_page + 1))}>Next</button></div></footer>}
    </article>
  </section>;
}
