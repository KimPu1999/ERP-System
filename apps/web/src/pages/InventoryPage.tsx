import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { getInventoryBalances, getInventoryReport } from '../lib/api';

export function InventoryPage() {
  const balances = useQuery({ queryKey: ['inventory-balances'], queryFn: getInventoryBalances });
  const report = useQuery({ queryKey: ['inventory-report'], queryFn: getInventoryReport });
  const isLoading = balances.isLoading || report.isLoading;
  const hasError = balances.isError || report.isError;
  const rows = balances.data?.data.data ?? [];
  const refresh = () => { void balances.refetch(); void report.refetch(); };

  return <section className="page-section">
    <div className="page-heading"><div><p className="eyebrow">Stock control</p><h1>Inventory</h1></div><button className="icon-button" type="button" aria-label="Refresh inventory" onClick={refresh}><RefreshCw size={16} /></button></div>
    <div className="metric-grid"><div className="metric"><span>Active SKUs</span><strong>{isLoading ? '…' : report.data?.data.total_skus ?? '—'}</strong></div><div className="metric"><span>Low stock alerts</span><strong>{isLoading ? '…' : report.data?.data.low_stock_alerts.length ?? '—'}</strong></div></div>
    {hasError && <div className="alert alert-danger" role="alert">Unable to load inventory data. <button className="table-action" type="button" onClick={refresh}>Retry</button></div>}
    {!hasError && <div className="table-card"><div className="table-card-header"><div><h2>Stock balances</h2><p className="muted">On-hand and reserved quantities by SKU and storage location.</p></div></div>{isLoading && <p className="muted">Loading inventory…</p>}{!isLoading && rows.length === 0 && <div className="empty-state"><strong>No inventory balances yet</strong><p className="muted">Post a goods receipt or authorized stock operation to see balances here.</p></div>}{!isLoading && rows.length > 0 && <div className="table-scroll"><table><thead><tr><th>SKU</th><th>Location</th><th>On hand</th><th>Reserved</th></tr></thead><tbody>{rows.map((balance) => <tr key={balance.id}><td>{balance.variant?.sku ?? balance.variant_id}</td><td>{balance.location?.name ?? balance.location_id}</td><td>{balance.quantity}</td><td>{balance.reserved_quantity}</td></tr>)}</tbody></table></div>}</div>}
  </section>;
}
