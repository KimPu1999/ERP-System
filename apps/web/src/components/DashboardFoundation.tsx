import { ArrowDownRight, ArrowUpRight, BarChart3, Boxes, CircleDollarSign, ClipboardCheck, FileText, Package, PackagePlus, ShoppingCart, TrendingUp, Truck, Warehouse, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { DashboardKpi, DashboardOverview } from '../lib/api';

type PanelProps = { title: string; eyebrow?: string; children: ReactNode; className?: string };
type Currency = DashboardOverview['currency']['code'];

const money = (value: string | number, currency: Currency) => currency ? new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value) || 0) : '—';
const number = (value: string | number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(Number(value) || 0);
const shortDate = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—';

export function DashboardFoundation({ data, onNavigate }: { data: DashboardOverview; onNavigate: (path: string) => void }) {
  const currency = data.currency.code;
  return <div className="dashboard-foundation">
    <section className="dashboard-section-block" aria-labelledby="dashboard-kpis-title"><SectionHeading id="dashboard-kpis-title" title="Business snapshot" subtitle={`${shortDate(data.filters.from)} – ${shortDate(data.filters.to)}`} /><div className="dashboard-kpi-grid"><KpiCard icon={CircleDollarSign} label="Total revenue" kpi={data.kpis.revenue} currency={currency} moneyValue /><KpiCard icon={ShoppingCart} label="Sales orders" kpi={data.kpis.sales_orders} /><KpiCard icon={FileText} label="Pending invoices" kpi={data.kpis.pending_invoices} /><KpiCard icon={Truck} label="Pending deliveries" kpi={data.kpis.pending_deliveries} /><KpiCard icon={Warehouse} label="Inventory value" kpi={data.kpis.inventory_value} currency={currency} moneyValue /><KpiCard icon={Package} label="Low stock items" kpi={data.kpis.low_stock_products} /></div>{!data.currency.configured && <p className="alert alert-warning" role="status">Configure a base accounting currency to display financial totals.</p>}</section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-analytics-title"><SectionHeading id="dashboard-analytics-title" title="Analytics overview" subtitle="Aggregated from approved ERP records." /><div className="foundation-analytics-grid"><DashboardPanel title="Revenue overview" eyebrow="Revenue vs expenses"><LineChart items={data.charts.revenue_expenses} currency={currency} /></DashboardPanel><DashboardPanel title="Growth & performance" eyebrow="Sales by category"><CategoryChart items={data.charts.sales_by_category} currency={currency} /></DashboardPanel><DashboardPanel title="Income, expense & profit" eyebrow="Financial performance" className="foundation-chart-wide"><FinancialBars items={data.charts.revenue_expenses} /></DashboardPanel><DashboardPanel title="Monthly sales trend" eyebrow="Issued invoice totals" className="foundation-chart-wide"><MonthlyChart items={data.charts.sales_trend} currency={currency} /></DashboardPanel></div></section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-business-title"><SectionHeading id="dashboard-business-title" title="Business performance" subtitle="Recent sales and product performance." /><div className="foundation-three-grid"><SalesTable title="Recent transactions" icon={FileText} data={data.recent_sales} currency={currency} onNavigate={onNavigate} /><ProductsTable title="Top products by sales" icon={BarChart3} rows={data.top_products} currency={currency} /><ProductsTable title="Top products by volume" icon={Boxes} rows={data.top_products_by_volume} currency={currency} volume /></div></section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-operations-title"><SectionHeading id="dashboard-operations-title" title="Operations monitoring" subtitle="Approvals, inventory alerts, and common workflows." /><div className="foundation-three-grid"><ApprovalsTable rows={data.pending_approvals} /><InventoryTable rows={data.inventory_alerts} /><QuickActions onNavigate={onNavigate} /></div></section>
  </div>;
}

export function DashboardFoundationLoading() {
  return <div className="dashboard-foundation" role="status" aria-label="Loading dashboard data"><div className="dashboard-kpi-grid">{Array.from({ length: 6 }, (_, index) => <div className="dashboard-panel foundation-skeleton-card" key={index} />)}</div><div className="foundation-analytics-grid"><div className="dashboard-panel foundation-skeleton-panel foundation-chart-wide" /><div className="dashboard-panel foundation-skeleton-panel" /><div className="dashboard-panel foundation-skeleton-panel" /></div></div>;
}

function KpiCard({ icon: Icon, label, kpi, currency, moneyValue = false }: { icon: LucideIcon; label: string; kpi: DashboardKpi; currency?: Currency; moneyValue?: boolean }) {
  const change = kpi.change_percent === null ? null : Number(kpi.change_percent);
  const value = moneyValue ? money(kpi.value, currency ?? null) : number(kpi.value);
  return <article className="dashboard-kpi foundation-kpi"><div className="kpi-top"><span className="kpi-icon"><Icon size={16} /></span><span className="kpi-label">{label}</span></div><strong>{value}</strong>{change === null ? <span className="kpi-note">No comparison available</span> : <span className={`kpi-change ${change >= 0 ? 'positive' : 'negative'}`}>{change >= 0 ? <ArrowUpRight size={13} /> : <ArrowDownRight size={13} />}{Math.abs(change).toFixed(1)}% vs previous period</span>}</article>;
}

function SectionHeading({ id, title, subtitle }: { id: string; title: string; subtitle: string }) { return <div className="dashboard-section-heading"><div><p className="dashboard-section-kicker">ERP Cloud</p><h2 id={id}>{title}</h2></div><p>{subtitle}</p></div>; }
function DashboardPanel({ title, eyebrow, children, className = '' }: PanelProps) { return <article className={`dashboard-panel foundation-panel ${className}`}><div className="panel-heading"><div><p className="panel-eyebrow">{eyebrow}</p><h3>{title}</h3></div></div>{children}</article>; }

function LineChart({ items, currency }: { items: DashboardOverview['charts']['revenue_expenses']; currency: Currency }) {
  if (items.length === 0) return <EmptyState message="No revenue or expense activity in this period." />;
  const max = Math.max(...items.flatMap((item) => [Number(item.revenue), Number(item.expenses)]), 1);
  const points = (key: 'revenue' | 'expenses') => items.map((item, index) => `${(index / Math.max(items.length - 1, 1)) * 100},${92 - (Number(item[key]) / max) * 78}`).join(' ');
  return <div className="chart-wrap"><div className="chart-legend"><span><i className="legend-dot revenue-dot" />Revenue</span><span><i className="legend-dot expense-dot" />Expenses</span><strong>{money(items.reduce((sum, item) => sum + Number(item.revenue), 0), currency)}</strong></div><svg className="line-chart" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Revenue versus expenses"><path d="M0 92H100M0 53H100M0 14H100" className="chart-grid-line" /><polyline points={points('expenses')} className="chart-line expenses-line" /><polyline points={points('revenue')} className="chart-line revenue-line" /></svg><div className="chart-axis"><span>{items[0]?.label}</span><span>{items[Math.floor(items.length / 2)]?.label}</span><span>{items.at(-1)?.label}</span></div></div>;
}

function CategoryChart({ items, currency }: { items: DashboardOverview['charts']['sales_by_category']; currency: Currency }) {
  if (items.length === 0) return <EmptyState message="No category sales in this period." />;
  const total = items.reduce((sum, item) => sum + Number(item.value), 0);
  let cursor = 0;
  const colors = ['#2563eb', '#14b8a6', '#f59e0b', '#8b5cf6', '#ec4899', '#94a3b8'];
  const stops = items.map((item, index) => { const start = cursor; cursor += total ? (Number(item.value) / total) * 100 : 0; return `${colors[index % colors.length]} ${start}% ${cursor}%`; }).join(', ');
  return <div className="donut-layout"><div className="donut" style={{ background: `conic-gradient(${stops})` }}><div><strong>{money(total, currency)}</strong><span>Total sales</span></div></div><div className="donut-legend">{items.map((item, index) => <span key={item.label}><i className="legend-dot" style={{ background: colors[index % colors.length] }} />{item.label}<strong>{money(item.value, currency)}</strong></span>)}</div></div>;
}

function FinancialBars({ items }: { items: DashboardOverview['charts']['revenue_expenses'] }) {
  if (items.length === 0) return <EmptyState message="No financial activity in this period." />;
  const sampled = items.slice(-8);
  const max = Math.max(...sampled.flatMap((item) => [Number(item.revenue), Number(item.expenses)]), 1);
  return <div className="foundation-bars-preview">{sampled.map((item) => { const revenue = Number(item.revenue); const expense = Number(item.expenses); return <div className="foundation-bar-group" key={item.label} title={`${item.label}: income ${revenue}, expense ${expense}`}><i className="bar-income" style={{ height: `${Math.max(8, (revenue / max) * 100)}%` }} /><i className="bar-expense" style={{ height: `${Math.max(8, (expense / max) * 100)}%` }} /><i className="bar-profit" style={{ height: `${Math.max(8, (Math.max(0, revenue - expense) / max) * 100)}%` }} /></div>; })}</div>;
}

function MonthlyChart({ items, currency }: { items: DashboardOverview['charts']['sales_trend']; currency: Currency }) {
  if (items.length === 0) return <EmptyState message="No monthly sales in this period." />;
  const max = Math.max(...items.map((item) => Number(item.value)), 1);
  return <div className="trend-chart">{items.map((item) => <div className="trend-column" key={item.label} title={`${item.label}: ${money(item.value, currency)}`}><div className="trend-bar" style={{ height: `${Math.max(4, (Number(item.value) / max) * 100)}%` }} /><span>{item.label.split(' ')[0]}</span></div>)}</div>;
}

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
function EmptyState({ message }: { message: string }) { return <div className="foundation-empty chart-empty"><span>{message}</span></div>; }
function QuickActions({ onNavigate }: { onNavigate: (path: string) => void }) { const actions = [{ label: 'New sales order', path: '/crm-sales', icon: ShoppingCart }, { label: 'Create invoice', path: '/crm-sales', icon: FileText }, { label: 'Add product', path: '/catalog', icon: PackagePlus }, { label: 'Purchase order', path: '/purchasing', icon: Truck }, { label: 'Stock transfer', path: '/inventory', icon: Warehouse }, { label: 'View reports', path: '/workflow', icon: BarChart3 }]; return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Quick actions" icon={TrendingUp} /><div className="foundation-actions">{actions.map(({ label, path, icon: Icon }) => <button key={label} onClick={() => onNavigate(path)}><Icon size={15} /><span>{label}</span><ArrowUpRight size={13} /></button>)}</div></article>; }
