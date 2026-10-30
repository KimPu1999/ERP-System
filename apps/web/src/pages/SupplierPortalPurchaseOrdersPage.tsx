import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, RefreshCw } from 'lucide-react';
import { acknowledgeSupplierPortalOrder, getSupplierPortalPurchaseOrders, type SupplierPortalOrder } from '../lib/api';

export function SupplierPortalPurchaseOrdersPage() {
  const [search, setSearch] = useState('');
  const client = useQueryClient();
  const q = useQuery({ queryKey: ['supplier-portal-orders', search], queryFn: () => getSupplierPortalPurchaseOrders({ search, per_page: 25 }) });
  const ack = useMutation({ mutationFn: (id: string) => acknowledgeSupplierPortalOrder(id, { type: 'acknowledged', idempotency_key: id + '-ack' }), onSuccess: () => client.invalidateQueries({ queryKey: ['supplier-portal-orders'] }) });
  return <section className="page-section"><header className="purchasing-page-header"><div><div className="breadcrumb-line"><span>Supplier Portal</span><span>/</span><span>Purchase Orders</span></div><h1>Purchase Orders</h1><p className="muted">Released purchase orders for your authorized buyer relationship.</p></div><button className="secondary" onClick={() => void q.refetch()}><RefreshCw size={15} /> Refresh</button></header><div className="purchasing-filter-toolbar"><label><span>Search PO</span><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="PO number" /></label></div><article className="purchasing-table-card table-card">{q.isError ? <p className="error-text">Unable to load purchase orders.</p> : <div className="table-scroll"><table><thead><tr><th>PO Number</th><th>Order Date</th><th>Delivery Date</th><th>Currency</th><th>Items</th><th>Action</th></tr></thead><tbody>{q.isLoading ? <tr><td colSpan={6}>Loading…</td></tr> : (q.data?.data.data ?? []).map((o: SupplierPortalOrder) => <tr key={o.id}><td><strong>{o.number}</strong></td><td>{o.order_date}</td><td>{o.expected_at ?? 'Not specified'}</td><td>{o.currency?.code ?? '—'}</td><td>{o.items?.length ?? 0}</td><td><button className="table-action" disabled={ack.isPending} onClick={() => ack.mutate(o.id)}><CheckCircle2 size={14} /> Acknowledge</button></td></tr>)}{!q.isLoading && !q.data?.data.data.length ? <tr><td colSpan={6} className="muted">No released purchase orders.</td></tr> : null}</tbody></table></div>}</article></section>;
}
