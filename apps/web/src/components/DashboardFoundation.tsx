import { ArrowUpRight, BarChart3, Boxes, ClipboardCheck, FileText, PackagePlus, ShoppingCart, TrendingUp, Truck, Warehouse, type LucideIcon } from 'lucide-react';
import { DashboardKpiGrid, DashboardKpiSkeleton } from './DashboardKpi';
import { DashboardAnalytics, type DashboardAnalyticsProps } from './DashboardAnalytics';
import { formatMoney as money, formatNumber as number } from '../lib/dashboardFormatting';
import type { DashboardOverview } from '../lib/api';

type Currency = DashboardOverview['currency']['code'];

const shortDate = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—';

export function DashboardFoundation({ data, onNavigate, analytics }: { data: DashboardOverview; onNavigate: (path: string) => void; analytics: DashboardAnalyticsProps }) {
  const currency = data.currency.code;
  return <div className="dashboard-foundation">
    <section className="dashboard-section-block" aria-labelledby="dashboard-kpis-title"><SectionHeading id="dashboard-kpis-title" title="Business snapshot" subtitle={`${shortDate(data.filters.from)} – ${shortDate(data.filters.to)}`} /><DashboardKpiGrid kpis={data.kpis} currency={currency} />{!data.currency.configured && <p className="alert alert-warning" role="status">Configure a base accounting currency to display financial totals.</p>}</section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-analytics-title"><SectionHeading id="dashboard-analytics-title" title="Analytics overview" subtitle="Interactive, filter-aware financial reporting." /><DashboardAnalytics {...analytics} /></section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-business-title"><SectionHeading id="dashboard-business-title" title="Business performance" subtitle="Recent sales and product performance." /><div className="foundation-three-grid"><SalesTable title="Recent transactions" icon={FileText} data={data.recent_sales} currency={currency} onNavigate={onNavigate} /><ProductsTable title="Top products by sales" icon={BarChart3} rows={data.top_products} currency={currency} /><ProductsTable title="Top products by volume" icon={Boxes} rows={data.top_products_by_volume} currency={currency} volume /></div></section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-operations-title"><SectionHeading id="dashboard-operations-title" title="Operations monitoring" subtitle="Approvals, inventory alerts, and common workflows." /><div className="foundation-three-grid"><ApprovalsTable rows={data.pending_approvals} /><InventoryTable rows={data.inventory_alerts} /><QuickActions onNavigate={onNavigate} /></div></section>
  </div>;
}

export function DashboardFoundationLoading() {
  return <div className="dashboard-foundation" role="status" aria-label="Loading dashboard data"><div className="dashboard-kpi-grid">{Array.from({ length: 6 }, (_, index) => <DashboardKpiSkeleton key={index} />)}</div><div className="foundation-analytics-grid"><div className="dashboard-panel foundation-skeleton-panel foundation-chart-wide" /><div className="dashboard-panel foundation-skeleton-panel" /><div className="dashboard-panel foundation-skeleton-panel" /></div></div>;
}

function SectionHeading({ id, title, subtitle }: { id: string; title: string; subtitle: string }) { return <div className="dashboard-section-heading"><div><p className="dashboard-section-kicker">ERP Cloud</p><h2 id={id}>{title}</h2></div><p>{subtitle}</p></div>; }

function SalesTable({ title, icon: Icon, data, currency, onNavigate }: { title: string; icon: LucideIcon; data: DashboardOverview['recent_sales']; currency: Currency; onNavigate: (path: string) => void }) {
  return <article className="dashboard-panel foundation-table-panel"><PanelTitle title={title} icon={Icon} action="View all" onAction={() => onNavigate('/crm-sales')} /><div className="foundation-table-wrap"><table className="dashboard-table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>{data.length === 0 ? <EmptyRow colSpan={5} message="No sales transactions in this period." /> : data.map((item) => <tr key={item.id}><td>{item.number}</td><td>{item.customer}</td><td>{shortDate(item.date)}</td><td>{money(item.amount, currency)}</td><td><span className="table-status">{item.status}</span></td></tr>)}</tbody></table></div></article>;
}

function ProductsTable({ title, icon: Icon, rows, currency, volume = false }: { title: string; icon: LucideIcon; rows: DashboardOverview['top_products']; currency: Currency; volume?: boolean }) {
  return <article className="dashboard-panel foundation-table-panel"><PanelTitle title={title} icon={Icon} /><div className="foundation-table-wrap"><table className="dashboard-table"><thead><tr><th>Product</th><th>SKU</th><th>{volume ? 'Units sold' : 'Revenue'}</th></tr></thead><tbody>{rows.length === 0 ? <EmptyRow colSpan={3} message="No product sales in this period." /> : rows.map((item) => <tr key={`${item.product}-${item.sku}`}><td>{item.product}</td><td>{item.sku}</td><td>{volume ? number(item.units_sold) : money(item.revenue, currency)}</td></tr>)}</tbody></table></div></article>;
}

function ApprovalsTable({ rows }: { rows: DashboardOverview['pending_approvals'] }) { return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Pending approvals" icon={ClipboardCheck} /><div className="foundation-table-wrap"><table className="dashboard-table"><thead><tr><th>Document</th><th>Requested by</th><th>Status</th></tr></thead><tbody>{rows.length === 0 ? <EmptyRow colSpan={3} message="No pending approvals." /> : rows.map((item) => <tr key={item.id}><td>{item.document_type}</td><td>{item.requested_by}</td><td><span className="table-status">{item.status}</span></td></tr>)}</tbody></table></div></article>; }
function InventoryTable({ rows }: { rows: DashboardOverview['inventory_alerts'] }) { return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Inventory alerts" icon={Warehouse} /><div className="foundation-table-wrap"><table className="dashboard-table"><thead><tr><th>Product</th><th>Warehouse</th><th>Available</th><th>Reorder</th></tr></thead><tbody>{rows.length === 0 ? <EmptyRow colSpan={4} message="No active inventory alerts." /> : rows.map((item) => <tr key={item.id}><td>{item.product}</td><td>{item.warehouse}</td><td>{number(item.available_stock)}</td><td>{number(item.reorder_level)}</td></tr>)}</tbody></table></div></article>; }

function PanelTitle({ title, icon: Icon, action, onAction }: { title: string; icon: LucideIcon; action?: string; onAction?: () => void }) { return <div className="panel-heading"><div className="foundation-title"><span className="panel-icon"><Icon size={15} /></span><h3>{title}</h3></div>{action && onAction ? <button className="table-action" onClick={onAction}>{action}</button> : null}</div>; }
function EmptyRow({ colSpan, message }: { colSpan: number; message: string }) { return <tr><td colSpan={colSpan}><div className="foundation-empty"><span>{message}</span></div></td></tr>; }
function QuickActions({ onNavigate }: { onNavigate: (path: string) => void }) { const actions = [{ label: 'New sales order', path: '/crm-sales', icon: ShoppingCart }, { label: 'Create invoice', path: '/crm-sales', icon: FileText }, { label: 'Add product', path: '/catalog', icon: PackagePlus }, { label: 'Purchase order', path: '/purchasing', icon: Truck }, { label: 'Stock transfer', path: '/inventory', icon: Warehouse }, { label: 'View reports', path: '/workflow', icon: BarChart3 }]; return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Quick actions" icon={TrendingUp} /><div className="foundation-actions">{actions.map(({ label, path, icon: Icon }) => <button key={label} onClick={() => onNavigate(path)}><Icon size={15} /><span>{label}</span><ArrowUpRight size={13} /></button>)}</div></article>; }
