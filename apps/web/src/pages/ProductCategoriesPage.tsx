import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { getProductCategories } from '../lib/api';

export function ProductCategoriesPage() {
  const [search, setSearch] = useState('');
  const categories = useQuery({ queryKey: ['product-categories', search], queryFn: () => getProductCategories({ search, per_page: 25 }) });
  const page = categories.data?.data;

  return <section className="page-section">
    <div className="page-heading"><div><p className="eyebrow">Products / Categories</p><h1>Product Categories</h1><p className="muted">Organize and manage product classifications.</p></div><button className="icon-button" type="button" aria-label="Refresh categories" onClick={() => void categories.refetch()}><RefreshCw size={16} /></button></div>
    <div className="toolbar"><input aria-label="Search categories" placeholder="Search category name or slug" value={search} onChange={(event) => setSearch(event.target.value)} /></div>
    <div className="table-card">
      {categories.isLoading && <p className="muted">Loading categories…</p>}
      {categories.isError && <div className="alert alert-danger">Unable to load categories. <button className="table-action" type="button" onClick={() => void categories.refetch()}>Retry</button></div>}
      {!categories.isLoading && !categories.isError && page?.data.length === 0 && <p className="muted">No product categories found.</p>}
      {!categories.isLoading && !categories.isError && page && page.data.length > 0 && <table><thead><tr><th>Category</th><th>Slug</th><th>Parent</th><th>Products</th><th>Subcategories</th><th>Updated</th></tr></thead><tbody>{page.data.map((category) => <tr key={category.id}><td>{category.name}</td><td>{category.slug}</td><td>{category.parent?.name ?? 'Root category'}</td><td>{category.direct_product_count}</td><td>{category.subcategory_count}</td><td>{new Date(category.updated_at).toLocaleDateString()}</td></tr>)}</tbody></table>}
      {page && page.last_page > 1 && <p className="muted">Page {page.current_page} of {page.last_page} · {page.total} categories</p>}
    </div>
  </section>;
}
