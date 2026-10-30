import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { getSupplierPortalDashboard } from '../lib/api';

export function SupplierPortalDashboardPage() {
  const q = useQuery({ queryKey: ['supplier-portal-dashboard'], queryFn: getSupplierPortalDashboard });
  const d = q.data?.data;
  const cards: Array<[string, number | undefined]> = [['Open purchase orders', d?.open_purchase_orders], ['Awaiting acknowledgement', d?.awaiting_acknowledgement], ['Upcoming deliveries', d?.upcoming_deliveries], ['Submitted shipments', d?.submitted_shipments], ['Open corrective actions', d?.open_corrective_actions], ['Unread conversations', d?.conversations]];
  return <section className="page-section"><header className="purchasing-page-header"><div><div className="breadcrumb-line"><span>Supplier Portal</span><span>/</span><span>Dashboard</span></div><p className="eyebrow">Buyer collaboration</p><h1>{d?.supplier?.trading_name ?? d?.supplier?.name ?? 'Supplier dashboard'}</h1></div><button className="secondary" onClick={() => void q.refetch()}><RefreshCw size={15} /> Refresh</button></header>{q.isError ? <div className="error-state">Unable to load supplier portal dashboard.</div> : q.isLoading ? <div className="loading-state">Loading dashboard…</div> : <div className="crm-grid">{cards.map(([label, value]) => <article className="crm-card" key={label}><span className="muted">{label}</span><strong>{value ?? 0}</strong></article>)}</div>}</section>;
}
