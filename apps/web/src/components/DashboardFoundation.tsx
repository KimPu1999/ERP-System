import { ArrowUpRight, BarChart3, ClipboardCheck, FileText, PackagePlus, ShoppingCart, TrendingUp, Truck, Warehouse, type LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';
import { BusinessPerformanceSection, type BusinessPerformanceProps } from './BusinessPerformance';
import { DashboardKpiGrid, DashboardKpiSkeleton } from './DashboardKpi';
import { DashboardAnalytics, type DashboardAnalyticsProps } from './DashboardAnalytics';
import { formatNumber as number } from '../lib/dashboardFormatting';
import type { DashboardOverview } from '../lib/api';

const shortDate = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—';

export function DashboardFoundation({ data, onNavigate, analytics, business }: { data: DashboardOverview; onNavigate: (path: string) => void; analytics: DashboardAnalyticsProps; business: Omit<BusinessPerformanceProps, 'onNavigate'> }) {
  const currency = data.currency.code;
  const companyProfilePath = data.filters.company_id ? `/organization/profile?company_id=${encodeURIComponent(data.filters.company_id)}&tab=localization` : '/organization/profile?tab=localization';
  return <div className="dashboard-foundation">
    <section className="dashboard-section-block" aria-labelledby="dashboard-kpis-title"><SectionHeading id="dashboard-kpis-title" title="Business snapshot" subtitle={`${shortDate(data.filters.from)} – ${shortDate(data.filters.to)}`} /><DashboardKpiGrid kpis={data.kpis} currency={currency} />{!data.currency.configured && <div className="dashboard-currency-notice" role="status"><span>Base accounting currency is required for financial totals.</span><Link to={companyProfilePath}>Configure currency</Link></div>}</section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-analytics-title"><SectionHeading id="dashboard-analytics-title" title="Analytics Overview" subtitle={`${shortDate(data.filters.from)} – ${shortDate(data.filters.to)}`} /><DashboardAnalytics {...analytics} /></section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-business-title"><SectionHeading id="dashboard-business-title" title="Business performance" subtitle="Authorized posted transactions and net product performance." /><BusinessPerformanceSection {...business} onNavigate={onNavigate} /></section>
    <section className="dashboard-section-block" aria-labelledby="dashboard-operations-title"><SectionHeading id="dashboard-operations-title" title="Operations monitoring" subtitle="Approvals, inventory alerts, and common workflows." /><div className="foundation-three-grid"><ApprovalsTable rows={data.pending_approvals} /><InventoryTable rows={data.inventory_alerts} /><QuickActions onNavigate={onNavigate} /></div></section>
  </div>;
}

export function DashboardFoundationLoading() {
  return <div className="dashboard-foundation" role="status" aria-label="Loading dashboard data"><div className="dashboard-kpi-grid">{Array.from({ length: 6 }, (_, index) => <DashboardKpiSkeleton key={index} />)}</div><div className="foundation-analytics-grid"><div className="dashboard-panel foundation-skeleton-panel foundation-chart-wide" /><div className="dashboard-panel foundation-skeleton-panel" /><div className="dashboard-panel foundation-skeleton-panel" /></div></div>;
}

function SectionHeading({ id, title, subtitle }: { id: string; title: string; subtitle: string }) { return <div className="dashboard-section-heading"><h2 id={id}>{title}</h2><p>{subtitle}</p></div>; }

function ApprovalsTable({ rows }: { rows: DashboardOverview['pending_approvals'] }) { return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Pending approvals" icon={ClipboardCheck} /><div className="foundation-table-wrap"><table className="dashboard-table"><thead><tr><th>Document</th><th>Requested by</th><th>Status</th></tr></thead><tbody>{rows.length === 0 ? <EmptyRow colSpan={3} message="No pending approvals." /> : rows.map((item) => <tr key={item.id}><td>{item.document_type}</td><td>{item.requested_by}</td><td><span className="table-status">{item.status}</span></td></tr>)}</tbody></table></div></article>; }
function InventoryTable({ rows }: { rows: DashboardOverview['inventory_alerts'] }) { return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Inventory alerts" icon={Warehouse} /><div className="foundation-table-wrap"><table className="dashboard-table"><thead><tr><th>Product</th><th>Warehouse</th><th>Available</th><th>Reorder</th></tr></thead><tbody>{rows.length === 0 ? <EmptyRow colSpan={4} message="No active inventory alerts." /> : rows.map((item) => <tr key={item.id}><td>{item.product}</td><td>{item.warehouse}</td><td>{number(item.available_stock)}</td><td>{number(item.reorder_level)}</td></tr>)}</tbody></table></div></article>; }

function PanelTitle({ title, icon: Icon, action, onAction }: { title: string; icon: LucideIcon; action?: string; onAction?: () => void }) { return <div className="panel-heading"><div className="foundation-title"><span className="panel-icon"><Icon size={15} /></span><h3>{title}</h3></div>{action && onAction ? <button className="table-action" onClick={onAction}>{action}</button> : null}</div>; }
function EmptyRow({ colSpan, message }: { colSpan: number; message: string }) { return <tr><td colSpan={colSpan}><div className="foundation-empty"><span>{message}</span></div></td></tr>; }
function QuickActions({ onNavigate }: { onNavigate: (path: string) => void }) { const actions = [{ label: 'New sales order', path: '/crm-sales', icon: ShoppingCart }, { label: 'Create invoice', path: '/crm-sales', icon: FileText }, { label: 'Add product', path: '/catalog', icon: PackagePlus }, { label: 'Purchase order', path: '/purchasing', icon: Truck }, { label: 'Stock transfer', path: '/inventory', icon: Warehouse }, { label: 'View reports', path: '/workflow', icon: BarChart3 }]; return <article className="dashboard-panel foundation-table-panel"><PanelTitle title="Quick actions" icon={TrendingUp} /><div className="foundation-actions">{actions.map(({ label, path, icon: Icon }) => <button key={label} onClick={() => onNavigate(path)}><Icon size={15} /><span>{label}</span><ArrowUpRight size={13} /></button>)}</div></article>; }
