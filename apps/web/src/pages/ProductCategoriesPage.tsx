import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, RefreshCw, RotateCcw, Search } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { getProductCategories } from '../lib/api';

const pageSizes = [10, 25, 50, 100] as const;

export function ProductCategoriesPage() {
  const [params, setParams] = useSearchParams();
  const search = params.get('search') ?? '';
  const page = Number(params.get('page') ?? 1);
  const perPage = pageSizes.includes(Number(params.get('per_page')) as (typeof pageSizes)[number]) ? Number(params.get('per_page')) as (typeof pageSizes)[number] : 25;
  const query = { search: search || undefined, page, per_page: perPage, sort: 'name', direction: 'asc' as const };
  const categories = useQuery({ queryKey: ['product-categories', 'catalog', query], queryFn: () => getProductCategories(query) });
  const result = categories.data?.data;

  const update = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value); else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next, { replace: true });
  };
  const clear = () => setParams(new URLSearchParams(), { replace: true });

  return <section className="page-section product-catalog-page product-categories-page">
    <header className="product-catalog-header"><div className="product-catalog-title"><Link className="product-catalog-back" to="/catalog" aria-label="Back to product catalog"><ArrowLeft size={17} /></Link><h1>Product Categories</h1></div><button className="icon-button" type="button" aria-label="Refresh categories" onClick={() => void categories.refetch()} disabled={categories.isFetching}><RefreshCw size={16} className={categories.isFetching ? 'product-catalog-spin' : undefined} /></button></header>
    <div className="product-catalog-filter-toolbar product-category-toolbar"><label className="product-catalog-search"><Search size={15} /><span className="sr-only">Search categories</span><input aria-label="Search categories" placeholder="Search category name or slug" value={search} onChange={(event) => update('search', event.target.value)} /></label><div className="product-catalog-filter-actions"><button className="secondary" type="button" disabled={!search && !params.get('per_page')} onClick={clear}><RotateCcw size={14} /> Clear</button><label className="product-catalog-rows"><span>Rows</span><select aria-label="Rows per page" value={perPage} onChange={(event) => update('per_page', event.target.value)}>{pageSizes.map((size) => <option value={size} key={size}>{size}</option>)}</select></label></div></div>
    {categories.isError && <div className="alert alert-danger product-catalog-alert" role="alert">Unable to load categories. <button className="text-link" type="button" onClick={() => void categories.refetch()}>Retry</button></div>}
    <article className="table-card product-catalog-table-card"><div className="product-catalog-table-heading"><div><h2>Categories</h2><span>{result ? result.total + ' categor' + (result.total === 1 ? 'y' : 'ies') : categories.isLoading ? 'Loading…' : 'No records'}</span></div></div><div className="table-scroll"><table><thead><tr><th>Category</th><th>Slug</th><th>Parent</th><th>Products</th><th>Subcategories</th><th>Updated</th></tr></thead><tbody>
      {categories.isLoading ? <tr><td colSpan={6}><div className="product-catalog-loading"><span /><span /><span /></div></td></tr> : (result?.data ?? []).map((category) => <tr key={category.id}><td><strong>{category.name}</strong></td><td><span className="product-catalog-secondary-value">{category.slug}</span></td><td>{category.parent?.name ?? 'Root category'}</td><td>{category.direct_product_count}</td><td>{category.subcategory_count}</td><td>{new Date(category.updated_at).toLocaleDateString()}</td></tr>)}
      {!categories.isLoading && !categories.isError && !result?.data.length && <tr><td colSpan={6}><div className="product-catalog-empty"><strong>No product categories found</strong><span>{search ? 'Try another search term.' : 'Categories will appear here when they are configured.'}</span></div></td></tr>}
    </tbody></table></div>{result && <footer className="product-catalog-pagination"><span>Page {result.current_page} of {result.last_page}</span><div><button className="secondary" type="button" disabled={result.current_page <= 1 || categories.isFetching} onClick={() => update('page', String(result.current_page - 1))}>Previous</button><button className="secondary" type="button" disabled={result.current_page >= result.last_page || categories.isFetching} onClick={() => update('page', String(result.current_page + 1))}>Next</button></div></footer>}</article>
  </section>;
}
