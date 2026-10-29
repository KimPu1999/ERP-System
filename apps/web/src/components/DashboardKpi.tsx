import { ArrowDownRight, ArrowUpRight, CircleDollarSign, FileText, Package, ShoppingCart, Truck, Warehouse, type LucideIcon } from 'lucide-react';
import type { DashboardKpi as DashboardKpiValue, DashboardOverview } from '../lib/api';
import { formatMoney, formatNumber } from '../lib/dashboardFormatting';

type Currency = DashboardOverview['currency']['code'];
type KpiDefinition = { key: keyof DashboardOverview['kpis']; label: string; icon: LucideIcon; description: string; money?: boolean };

const definitions: KpiDefinition[] = [
  { key: 'revenue', label: 'Total revenue', icon: CircleDollarSign, description: 'Posted revenue in the selected period', money: true },
  { key: 'sales_orders', label: 'Sales orders', icon: ShoppingCart, description: 'Qualifying orders in the selected period' },
  { key: 'pending_invoices', label: 'Pending invoices', icon: FileText, description: 'Issued invoices with an outstanding balance' },
  { key: 'pending_deliveries', label: 'Pending deliveries', icon: Truck, description: 'Orders with quantities still to fulfill' },
  { key: 'inventory_value', label: 'Inventory value', icon: Warehouse, description: 'Current cost-based stock valuation', money: true },
  { key: 'low_stock_products', label: 'Low stock alerts', icon: Package, description: 'Active reorder-threshold alerts' },
];

export function DashboardKpiGrid({ kpis, currency }: { kpis: DashboardOverview['kpis']; currency: Currency }) {
  return <div className="dashboard-kpi-grid">{definitions.map((definition) => <DashboardKpiCard key={definition.key} definition={definition} kpi={kpis[definition.key]} currency={currency} />)}</div>;
}

export function DashboardKpiCard({ definition, kpi, currency }: { definition: KpiDefinition; kpi: DashboardKpiValue; currency: Currency }) {
  const Icon = definition.icon;
  const available = kpi.available !== false;
  const value = available ? (definition.money ? formatMoney(kpi.value, currency) : formatNumber(kpi.value)) : 'Unavailable';
  const change = kpi.change_percent === null ? null : Number(kpi.change_percent);
  const supportingText = available ? (change === null ? definition.description : `${Math.abs(change).toFixed(1)}% vs previous period`) : (kpi.reason ?? 'This metric is not available yet.');
  return <article className={`dashboard-kpi foundation-kpi ${available ? '' : 'is-unavailable'}`} aria-label={`${definition.label}: ${value}`}><div className="kpi-top"><span className="kpi-icon"><Icon size={16} aria-hidden="true" /></span><span className="kpi-label">{definition.label}</span></div><strong>{value}</strong>{available && change !== null ? <span className={`kpi-change ${change >= 0 ? 'positive' : 'negative'}`}>{change >= 0 ? <ArrowUpRight size={13} aria-hidden="true" /> : <ArrowDownRight size={13} aria-hidden="true" />}{supportingText}</span> : <span className="kpi-note">{supportingText}</span>}</article>;
}

export function DashboardKpiSkeleton() { return <div className="dashboard-panel foundation-skeleton-card" aria-hidden="true" />; }
export function DashboardEmptyState({ message }: { message: string }) { return <div className="foundation-empty chart-empty" role="status"><span>{message}</span></div>; }

