import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { getProducts } from '../lib/api';

export function ProductCatalogPage() {
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  useEffect(() => { const timer = window.setTimeout(() => { setDebouncedSearch(search); setPage(1); }, 300); return () => window.clearTimeout(timer); }, [search]);
  const products = useQuery({ queryKey: ['products', debouncedSearch, page, perPage], queryFn: () => getProducts({ search: debouncedSearch || undefined, page, per_page: perPage }) });
  const result = products.data?.data;
  const rows = result?.data.flatMap((product) => product.variants.map((variant) => ({ product, variant }))) ?? [];

  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">Products</p><h1>Product Catalog</h1><p className="muted">Search products by name, SKU or barcode.</p></div></div><div className="toolbar"><input aria-label="Search products" placeholder="Search products, SKU or barcode" value={search} onChange={(event) => setSearch(event.target.value)} /><select aria-label="Rows per page" value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }}><option value={10}>10 rows</option><option value={25}>25 rows</option><option value={50}>50 rows</option><option value={100}>100 rows</option></select></div><div className="table-card">{products.isLoading && <p className="muted">Loading products…</p>}{products.isError && <div className="alert alert-danger">Unable to load products. <button className="table-action" type="button" onClick={() => void products.refetch()}>Retry</button></div>}{!products.isLoading && !products.isError && rows.length === 0 && <p className="muted">No products found.</p>}{rows.length > 0 && <table><thead><tr><th>Product</th><th>SKU</th><th>Internal barcode</th><th>Price</th><th>Status</th></tr></thead><tbody>{rows.map(({ product, variant }) => <tr key={variant.id}><td>{product.name}</td><td>{variant.sku}</td><td>{variant.internal_barcode}</td><td>{variant.price}</td><td>{product.status}</td></tr>)}</tbody></table>}{result && <div className="toolbar"><span className="muted">{result.total} products · Page {result.current_page} of {result.last_page}</span><div><button type="button" className="table-action" disabled={result.current_page <= 1} onClick={() => setPage((current) => current - 1)}>Previous</button><button type="button" className="table-action" disabled={result.current_page >= result.last_page} onClick={() => setPage((current) => current + 1)}>Next</button></div></div>}</div></section>;
}
