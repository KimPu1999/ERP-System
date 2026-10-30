import { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Boxes, CircleSlash2, Package, RefreshCw, RotateCcw, Search, Warehouse as WarehouseIcon } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { getDashboardBranches, getDashboardCompanies, getInventoryBalances, getInventoryReport, getWarehouses } from '../lib/api';
import { useI18n } from '../lib/i18n';

type FilterState = { company_id: string; branch_id: string; warehouse_id: string; search: string; status: '' | 'available' | 'low_stock' | 'out_of_stock'; sort: 'updated_at' | 'quantity' | 'reserved_quantity'; direction: 'asc' | 'desc'; page: number; per_page: number };
const readFilters = (params: URLSearchParams): FilterState => ({ company_id: params.get('company_id') ?? '', branch_id: params.get('branch_id') ?? '', warehouse_id: params.get('warehouse_id') ?? '', search: params.get('search') ?? '', status: (params.get('status') as FilterState['status']) || '', sort: (params.get('sort') as FilterState['sort']) || 'updated_at', direction: params.get('direction') === 'asc' ? 'asc' : 'desc', page: Math.max(Number(params.get('page') ?? 1) || 1, 1), per_page: [10, 25, 50, 100].includes(Number(params.get('per_page'))) ? Number(params.get('per_page')) : 25 });
export function InventoryPage() {
  const { t, formatNumber } = useI18n();
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => readFilters(searchParams), [searchParams]);
  const companies = useQuery({ queryKey: ['inventory-companies'], queryFn: getDashboardCompanies });
  const companyRows = useMemo(() => companies.data?.data.data ?? [], [companies.data]);
  const selectedCompanyId = filters.company_id || (companyRows.length === 1 ? companyRows[0].id : '');
  const branches = useQuery({ queryKey: ['inventory-branches', selectedCompanyId], queryFn: () => getDashboardBranches(selectedCompanyId), enabled: Boolean(selectedCompanyId) });
  const warehouses = useQuery({ queryKey: ['inventory-warehouses', selectedCompanyId, filters.branch_id], queryFn: () => getWarehouses(selectedCompanyId, filters.branch_id || undefined), enabled: Boolean(selectedCompanyId) });
  const queryFilters = { company_id: selectedCompanyId || undefined, branch_id: filters.branch_id || undefined, warehouse_id: filters.warehouse_id || undefined, search: filters.search || undefined, status: filters.status || undefined, sort: filters.sort, direction: filters.direction, page: filters.page, per_page: filters.per_page };
  const balances = useQuery({ queryKey: ['inventory-balances', queryFilters], queryFn: () => getInventoryBalances(queryFilters), enabled: Boolean(selectedCompanyId) });
  const report = useQuery({ queryKey: ['inventory-report', selectedCompanyId, filters.branch_id, filters.warehouse_id], queryFn: () => getInventoryReport({ company_id: selectedCompanyId || undefined, branch_id: filters.branch_id || undefined, warehouse_id: filters.warehouse_id || undefined }), enabled: Boolean(selectedCompanyId) });
  const formatQuantity = (value?: string | number | null) => formatNumber(value, { maximumFractionDigits: 6 });
  const statusLabel = (status: FilterState['status'] | 'available' | 'low_stock' | 'out_of_stock') => ({ available: t('inventory.available'), low_stock: t('inventory.lowStock'), out_of_stock: t('inventory.outOfStock'), '': t('inventory.allStock') }[status]);
  const rows = balances.data?.data.data ?? [];
  const reportData = report.data?.data;
  const isLoading = companies.isLoading || balances.isLoading || report.isLoading;
  const hasError = companies.isError || balances.isError || report.isError;

  useEffect(() => { if (!filters.company_id && companyRows.length === 1) setSearchParams((current) => { current.set('company_id', companyRows[0].id); current.delete('page'); return current; }, { replace: true }); }, [companyRows, filters.company_id, setSearchParams]);
  const updateFilters = (changes: Partial<FilterState>) => setSearchParams((current) => { const next = { ...filters, ...changes }; Object.entries(next).forEach(([key, value]) => { if (value === '' || value === undefined || (key === 'page' && value === 1) || (key === 'per_page' && value === 25) || (key === 'sort' && value === 'updated_at') || (key === 'direction' && value === 'desc')) current.delete(key); else current.set(key, String(value)); }); return current; });
  const refresh = () => { void companies.refetch(); void branches.refetch(); void warehouses.refetch(); void balances.refetch(); void report.refetch(); };
  const clearFilters = () => setSearchParams(selectedCompanyId ? { company_id: selectedCompanyId } : {});
  const branchRows = branches.data?.data.data ?? [];
  const warehouseRows = warehouses.data?.data.data ?? [];
  const pagination = balances.data?.data;

  return <section className="inventory-page">
    <header className="inventory-page-header"><div><h1>{t('inventory.title')}</h1><p>{t('inventory.description')}</p></div><button className="inventory-refresh" type="button" onClick={refresh} disabled={isLoading} aria-label={t('common.refresh')}><RefreshCw size={16} className={isLoading ? 'is-spinning' : ''} /><span>{t('common.refresh')}</span></button></header>
    <div className="inventory-filter-toolbar" role="search" aria-label={t('inventory.title')}>
      <label className="inventory-search"><Search size={16} aria-hidden="true" /><span className="sr-only">{t('common.search')}</span><input value={filters.search} onChange={(event) => updateFilters({ search: event.target.value, page: 1 })} placeholder={t('inventory.search')} /></label>
      <label><span>{t('inventory.company')}</span><select value={selectedCompanyId} onChange={(event) => updateFilters({ company_id: event.target.value, branch_id: '', warehouse_id: '', page: 1 })}><option value="">{t('app.allCompanies')}</option>{companyRows.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label>
      <label><span>{t('inventory.branch')}</span><select value={filters.branch_id} onChange={(event) => updateFilters({ branch_id: event.target.value, warehouse_id: '', page: 1 })} disabled={!selectedCompanyId}><option value="">{t('app.allBranches')}</option>{branchRows.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
      <label><span>{t('inventory.warehouse')}</span><select value={filters.warehouse_id} onChange={(event) => updateFilters({ warehouse_id: event.target.value, page: 1 })} disabled={!selectedCompanyId}><option value="">{t('inventory.warehouse')}</option>{warehouseRows.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>)}</select></label>
      <label><span>{t('inventory.status')}</span><select value={filters.status} onChange={(event) => updateFilters({ status: event.target.value as FilterState['status'], page: 1 })}><option value="">{t('inventory.allStock')}</option><option value="available">{t('inventory.available')}</option><option value="low_stock">{t('inventory.lowStock')}</option><option value="out_of_stock">{t('inventory.outOfStock')}</option></select></label>
      <label><span>{t('inventory.sort')}</span><select value={`${filters.sort}:${filters.direction}`} onChange={(event) => { const [sort, direction] = event.target.value.split(':') as [FilterState['sort'], FilterState['direction']]; updateFilters({ sort, direction, page: 1 }); }}><option value="updated_at:desc">{t('inventory.recentlyUpdated')}</option><option value="quantity:asc">{t('inventory.lowestOnHand')}</option><option value="quantity:desc">{t('inventory.highestOnHand')}</option><option value="reserved_quantity:desc">{t('inventory.highestReserved')}</option></select></label>
      <button className="inventory-clear" type="button" onClick={clearFilters} disabled={!filters.search && !filters.branch_id && !filters.warehouse_id && !filters.status && !filters.company_id}><RotateCcw size={15} /> {t('inventory.clear')}</button>
    </div>
    {hasError && <div className="inventory-alert" role="alert">{t('inventory.error')} <button type="button" onClick={refresh}>{t('common.retry')}</button></div>}
    {!hasError && !selectedCompanyId && !companies.isLoading && <div className="inventory-empty"><Boxes size={22} /><strong>{t('common.selectCompany')}</strong><span>{t('inventory.noCompany')}</span></div>}
    {selectedCompanyId && !hasError && <>
      <div className="inventory-kpi-grid">
        <article className="inventory-kpi"><span className="inventory-kpi-icon blue"><Package size={18} /></span><div><small>{t('inventory.activeSkus')}</small><strong>{isLoading ? '…' : reportData?.total_skus ?? '—'}</strong></div></article>
        <article className="inventory-kpi"><span className="inventory-kpi-icon indigo"><Boxes size={18} /></span><div><small>{t('inventory.availableStock')}</small><strong>{isLoading ? '…' : formatQuantity(reportData?.available_quantity)}</strong></div></article>
        <article className="inventory-kpi"><span className="inventory-kpi-icon orange"><AlertTriangle size={18} /></span><div><small>{t('inventory.lowStockAlerts')}</small><strong>{isLoading ? '…' : reportData?.low_stock_alerts.length ?? '—'}</strong></div></article>
        <article className="inventory-kpi"><span className="inventory-kpi-icon red"><CircleSlash2 size={18} /></span><div><small>{t('inventory.outOfStock')}</small><strong>{isLoading ? '…' : reportData?.out_of_stock_count ?? '—'}</strong></div></article>
        <article className="inventory-kpi"><span className="inventory-kpi-icon purple"><WarehouseIcon size={18} /></span><div><small>{t('inventory.reservedStock')}</small><strong>{isLoading ? '…' : formatQuantity(reportData?.reserved_quantity)}</strong></div></article>
        <article className="inventory-kpi inventory-kpi-muted"><span className="inventory-kpi-icon gray"><Package size={18} /></span><div><small>{t('inventory.inventoryValue')}</small><strong>—</strong><em>{t('inventory.costingUnavailable')}</em></div></article>
      </div>
      <div className="inventory-table-card">
        <div className="inventory-table-heading"><div><h2>{t('inventory.stockBalances')}</h2><span>{pagination?.total ?? 0} {t('inventory.balanceRows')}</span></div><label className="inventory-page-size"><span>{t('common.rows')}</span><select value={filters.per_page} onChange={(event) => updateFilters({ per_page: Number(event.target.value), page: 1 })}><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label></div>
        {isLoading && <div className="inventory-skeleton" aria-label="Loading inventory"><i /><i /><i /><i /></div>}
        {!isLoading && rows.length === 0 && <div className="inventory-empty compact"><Package size={22} /><strong>{t('inventory.noBalances')}</strong><span>{t('inventory.postOperation')}</span></div>}
        {!isLoading && rows.length > 0 && <div className="inventory-table-scroll"><table className="inventory-table"><thead><tr><th>{t('inventory.product')}</th><th>{t('inventory.sku')}</th><th>{t('inventory.warehouseLocation')}</th><th>{t('inventory.onHand')}</th><th>{t('inventory.reserved')}</th><th>{t('inventory.available')}</th><th>{t('inventory.reorderLevel')}</th><th>{t('inventory.status')}</th></tr></thead><tbody>{rows.map((balance) => <tr key={balance.id}><td><strong>{balance.variant?.product?.name ?? t('inventory.unknownProduct')}</strong></td><td><code>{balance.variant?.sku ?? balance.variant_id}</code></td><td><strong>{balance.location?.warehouse?.name ?? t('inventory.warehouse')}</strong><small>{balance.location?.name ?? balance.location_id}</small></td><td>{formatQuantity(balance.quantity)}</td><td>{formatQuantity(balance.reserved_quantity)}</td><td>{formatQuantity(balance.available_quantity)}</td><td>{formatQuantity(balance.reorder_point)}</td><td><span className={`inventory-status ${balance.status}`}>{statusLabel(balance.status)}</span></td></tr>)}</tbody></table></div>}
        {!isLoading && pagination && pagination.last_page > 1 && <div className="inventory-pagination"><span>{t('common.page')} {pagination.current_page} {t('common.of')} {pagination.last_page}</span><div><button type="button" disabled={pagination.current_page <= 1} onClick={() => updateFilters({ page: pagination.current_page - 1 })}>{t('common.previous')}</button><button type="button" disabled={pagination.current_page >= pagination.last_page} onClick={() => updateFilters({ page: pagination.current_page + 1 })}>{t('common.next')}</button></div></div>}
      </div>
    </>}
  </section>;
}
