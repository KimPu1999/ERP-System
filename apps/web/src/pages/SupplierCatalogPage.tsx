import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BarChart3, Building2, CircleAlert, ClipboardList, PackageSearch, Plus, RefreshCw, Search, Tags, X } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { createSupplierCatalogProduct, getDashboardCompanies, getSupplierCatalogProducts, getSupplierCatalogSummary, getSuppliers, type SupplierCatalogProduct } from '../lib/api';

type CatalogForm = {
  company_id: string;
  supplier_id: string;
  supplier_sku: string;
  supplier_name: string;
  supplier_barcode: string;
  purchase_uom: string;
  minimum_order_quantity: string;
  order_multiple: string;
  base_purchase_price: string;
};

const newCatalogForm = (companyId: string): CatalogForm => ({
  company_id: companyId,
  supplier_id: '',
  supplier_sku: '',
  supplier_name: '',
  supplier_barcode: '',
  purchase_uom: '',
  minimum_order_quantity: '0',
  order_multiple: '0',
  base_purchase_price: '',
});

const errorMessage = (error: unknown): string => error instanceof Error ? error.message : 'Request failed. Please try again.';

function StatusBadge({ value }: { value: string }) {
  const state = value.toLowerCase();
  return <span className={`status-badge ${state === 'active' ? 'active' : 'neutral'}`}>{value.replaceAll('_', ' ')}</span>;
}

export function SupplierCatalogPage() {
  const [params, setParams] = useSearchParams();
  const queryCompanyId = params.get('company_id') ?? '';
  const [search, setSearch] = useState(params.get('search') ?? '');
  const [page, setPage] = useState(Number(params.get('page') ?? 1));
  const [perPage, setPerPage] = useState<10 | 25 | 50 | 100>(([10, 25, 50, 100] as const).includes(Number(params.get('per_page')) as 10 | 25 | 50 | 100) ? Number(params.get('per_page')) as 10 | 25 | 50 | 100 : 25);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<CatalogForm>(newCatalogForm(queryCompanyId));
  const queryClient = useQueryClient();

  const companies = useQuery({ queryKey: ['dashboard-companies', 'supplier-catalog'], queryFn: getDashboardCompanies });
  const companyId = queryCompanyId || companies.data?.data.data[0]?.id || '';
  const companyName = companies.data?.data.data.find((company) => company.id === companyId)?.name ?? 'Select company';
  const suppliers = useQuery({
    queryKey: ['suppliers', 'supplier-catalog-selector', companyId],
    queryFn: () => getSuppliers({ company_id: companyId, status: 'active', per_page: 100 }),
    enabled: Boolean(companyId),
  });
  const summary = useQuery({
    queryKey: ['supplier-catalog-summary', companyId],
    queryFn: () => getSupplierCatalogSummary(companyId),
    enabled: Boolean(companyId),
  });
  const products = useQuery({
    queryKey: ['supplier-catalog-products', companyId, search, page, perPage],
    queryFn: () => getSupplierCatalogProducts({ company_id: companyId, search: search || undefined, page, per_page: perPage }),
    enabled: Boolean(companyId),
  });

  useEffect(() => {
    setForm((current) => current.company_id === companyId ? current : newCatalogForm(companyId));
  }, [companyId]);

  const updateUrl = (next: { company_id?: string; search?: string; page?: number; per_page?: number }) => {
    const nextParams = new URLSearchParams(params);
    if (next.company_id !== undefined) {
      if (next.company_id) nextParams.set('company_id', next.company_id);
      else nextParams.delete('company_id');
    }
    if (next.search !== undefined) {
      if (next.search) nextParams.set('search', next.search);
      else nextParams.delete('search');
    }
    if (next.page !== undefined) nextParams.set('page', String(next.page));
    if (next.per_page !== undefined) nextParams.set('per_page', String(next.per_page));
    setParams(nextParams, { replace: true });
  };

  const mutation = useMutation({
    mutationFn: () => createSupplierCatalogProduct(form),
    onSuccess: () => {
      setShowForm(false);
      setForm(newCatalogForm(companyId));
      void queryClient.invalidateQueries({ queryKey: ['supplier-catalog-products'] });
      void queryClient.invalidateQueries({ queryKey: ['supplier-catalog-summary'] });
    },
  });

  const cards = useMemo(() => [
    { label: 'Catalog products', value: summary.data?.data.supplier_products, icon: ClipboardList, tone: 'blue' },
    { label: 'Active price lists', value: summary.data?.data.active_price_lists, icon: Tags, tone: 'indigo' },
    { label: 'Active contracts', value: summary.data?.data.active_contracts, icon: BarChart3, tone: 'green' },
    { label: 'Unmapped products', value: summary.data?.data.unmapped_supplier_products, icon: PackageSearch, tone: 'orange' },
  ] as const, [summary.data]);
  const rows = products.data?.data.data ?? [];
  const pagination = products.data?.data;
  const supplierOptions = suppliers.data?.data.data ?? [];

  const selectCompany = (value: string) => {
    setPage(1);
    setSearch('');
    setShowForm(false);
    updateUrl({ company_id: value, search: '', page: 1 });
  };

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    mutation.mutate();
  };

  const refresh = () => {
    void Promise.all([summary.refetch(), products.refetch(), suppliers.refetch()]);
  };

  return <section className="purchasing-page page-section supplier-catalog-page">
    <header className="purchasing-page-header supplier-catalog-header">
      <div><h1>Supplier Catalogs</h1></div>
      <div className="purchasing-page-actions supplier-catalog-actions">
        <label className="supplier-catalog-company"><Building2 size={15} /><span>Company</span><select aria-label="Select supplier catalog company" value={companyId} onChange={(event) => selectCompany(event.target.value)} disabled={companies.isLoading || !companies.data?.data.data.length}>
          {!companies.data?.data.data.length && <option value="">{companies.isLoading ? 'Loading…' : 'No authorized company'}</option>}
          {companies.data?.data.data.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}
        </select></label>
        <button className="secondary" type="button" onClick={refresh} disabled={!companyId || products.isFetching}><RefreshCw size={15} className={products.isFetching ? 'spin' : undefined} /> Refresh</button>
        <button className="primary" type="button" onClick={() => setShowForm(true)} disabled={!companyId}><Plus size={15} /> Add catalog product</button>
      </div>
    </header>

    {companies.isError && <div className="alert alert-danger supplier-catalog-alert" role="alert"><CircleAlert size={15} />{errorMessage(companies.error)} <button className="text-link" type="button" onClick={() => void companies.refetch()}>Retry</button></div>}
    {!companyId && !companies.isLoading ? <div className="supplier-catalog-state"><Building2 size={22} /><strong>No authorized company selected</strong><span>Select an authorized company to load supplier catalog data.</span></div> : <>
      <div className="supplier-catalog-kpi-grid">
        {cards.map(({ label, value, icon: Icon, tone }) => <article className={`supplier-catalog-kpi supplier-catalog-kpi-${tone}`} key={label}><span className="supplier-catalog-kpi-icon"><Icon size={16} /></span><div><span>{label}</span><strong>{summary.isLoading ? '—' : summary.isError ? 'Unavailable' : value ?? 0}</strong></div></article>)}
      </div>
      {summary.isError && <div className="alert alert-danger supplier-catalog-alert" role="alert"><CircleAlert size={15} />Unable to load catalog summary for {companyName}. <button className="text-link" type="button" onClick={() => void summary.refetch()}>Retry</button></div>}

      <div className="supplier-catalog-toolbar">
        <label className="supplier-catalog-search"><Search size={15} /><span className="sr-only">Search supplier catalog</span><input aria-label="Search supplier catalog" placeholder="Search SKU, supplier product or barcode" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); updateUrl({ search: event.target.value, page: 1 }); }} /></label>
        <div className="supplier-catalog-toolbar-meta">{products.isFetching && !products.isLoading ? <span>Refreshing…</span> : pagination ? <span>{pagination.total} record{pagination.total === 1 ? '' : 's'}</span> : null}<label>Rows<select aria-label="Rows per page" value={perPage} onChange={(event) => { const value = Number(event.target.value) as 10 | 25 | 50 | 100; setPerPage(value); setPage(1); updateUrl({ per_page: value, page: 1 }); }}><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label></div>
      </div>

      {products.isError ? <div className="supplier-catalog-state supplier-catalog-state-error"><CircleAlert size={22} /><strong>Unable to load supplier catalogs</strong><span>{errorMessage(products.error)}</span><button className="secondary" type="button" onClick={() => void products.refetch()}>Retry</button></div> : <article className="table-card supplier-catalog-table-card">
        <div className="table-scroll"><table><thead><tr><th>Supplier SKU</th><th>Supplier</th><th>Supplier product</th><th>Internal mapping</th><th>MOQ / multiple</th><th>Price</th><th>Status</th></tr></thead><tbody>
          {products.isLoading ? <tr><td colSpan={7}><div className="supplier-catalog-table-loading"><span /><span /><span /></div></td></tr> : rows.map((product: SupplierCatalogProduct) => <tr key={product.id}><td><strong className="table-primary mono">{product.supplier_sku}</strong></td><td>{product.supplier?.name ?? '—'}<small>{product.supplier?.code ?? 'Authorized supplier'}</small></td><td><span className="table-primary">{product.supplier_name}</span><small>{product.supplier_barcode ?? 'No barcode'}</small></td><td>{product.variant?.product?.name ?? <span className="muted">Unmapped</span>}{product.variant?.sku && <small>{product.variant.sku}</small>}</td><td>{product.minimum_order_quantity} / {product.order_multiple}<small>{product.purchase_uom ?? 'Unit not set'}</small></td><td>{product.base_purchase_price ? `${product.base_purchase_price} ${product.currency?.code ?? ''}` : '—'}</td><td><StatusBadge value={product.status} /></td></tr>)}
          {!products.isLoading && rows.length === 0 && <tr><td colSpan={7}><div className="supplier-catalog-empty"><PackageSearch size={22} /><strong>No supplier catalog products</strong><span>{search ? 'Try a different search term.' : `No catalog products are configured for ${companyName}.`}</span>{!search && <button className="primary" type="button" onClick={() => setShowForm(true)}>Add catalog product</button>}</div></td></tr>}
        </tbody></table></div>
        {pagination && pagination.last_page > 1 && <footer className="supplier-catalog-pagination"><span>Page {pagination.current_page} of {pagination.last_page}</span><div><button className="secondary" type="button" disabled={pagination.current_page <= 1 || products.isFetching} onClick={() => { const next = pagination.current_page - 1; setPage(next); updateUrl({ page: next }); }}>Previous</button><button className="secondary" type="button" disabled={pagination.current_page >= pagination.last_page || products.isFetching} onClick={() => { const next = pagination.current_page + 1; setPage(next); updateUrl({ page: next }); }}>Next</button></div></footer>}
      </article>}
    </>}

    {showForm && companyId && <div className="supplier-catalog-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.currentTarget === event.target) setShowForm(false); }}><form className="supplier-catalog-modal" role="dialog" aria-modal="true" aria-labelledby="supplier-catalog-modal-title" onSubmit={submit}><header><div><span className="eyebrow">{companyName}</span><h2 id="supplier-catalog-modal-title">Add catalog product</h2></div><button className="icon-button" type="button" aria-label="Close form" onClick={() => setShowForm(false)}><X size={17} /></button></header><div className="supplier-catalog-form-grid">
      <label>Supplier<select required value={form.supplier_id} onChange={(event) => { const supplier = supplierOptions.find((item) => item.id === event.target.value); setForm({ ...form, supplier_id: event.target.value, supplier_name: form.supplier_name || supplier?.name || '' }); }}><option value="">Select active supplier</option>{supplierOptions.map((supplier) => <option value={supplier.id} key={supplier.id}>{supplier.name} ({supplier.code})</option>)}</select></label>
      <label>Supplier SKU<input required value={form.supplier_sku} onChange={(event) => setForm({ ...form, supplier_sku: event.target.value })} /></label>
      <label>Supplier product name<input required value={form.supplier_name} onChange={(event) => setForm({ ...form, supplier_name: event.target.value })} /></label>
      <label>Supplier barcode <span className="optional-label">optional</span><input value={form.supplier_barcode} onChange={(event) => setForm({ ...form, supplier_barcode: event.target.value })} /></label>
      <label>Purchase UOM <span className="optional-label">optional</span><input value={form.purchase_uom} onChange={(event) => setForm({ ...form, purchase_uom: event.target.value })} placeholder="Each, box, carton" /></label>
      <label>Minimum order quantity<input inputMode="decimal" value={form.minimum_order_quantity} onChange={(event) => setForm({ ...form, minimum_order_quantity: event.target.value })} /></label>
      <label>Order multiple<input inputMode="decimal" value={form.order_multiple} onChange={(event) => setForm({ ...form, order_multiple: event.target.value })} /></label>
      <label>Base purchase price <span className="optional-label">optional</span><input inputMode="decimal" value={form.base_purchase_price} onChange={(event) => setForm({ ...form, base_purchase_price: event.target.value })} /></label>
    </div>{suppliers.isError && <p className="supplier-catalog-form-note error-text">Unable to load active suppliers. Close and retry after the supplier list is available.</p>}{mutation.isError && <p className="supplier-catalog-form-note error-text">{errorMessage(mutation.error)}</p>}<footer><button className="secondary" type="button" onClick={() => setShowForm(false)}>Cancel</button><button className="primary" type="submit" disabled={mutation.isPending || suppliers.isLoading || !supplierOptions.length}>{mutation.isPending ? 'Saving…' : 'Save catalog product'}</button></footer></form></div>}
  </section>;
}
