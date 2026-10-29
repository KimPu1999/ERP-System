import { ArrowDownLeft, ArrowUpRight, BarChart3, Boxes, ExternalLink, FileText, RefreshCw, ReceiptText, type LucideIcon } from 'lucide-react';
import { formatMoney, formatNumber } from '../lib/dashboardFormatting';
import type { DashboardProductRanking, DashboardRecentTransactions, DashboardTransaction } from '../lib/api';

type PanelProps = { onNavigate: (path: string) => void };
export type BusinessPerformanceProps = PanelProps & {
  recent?: DashboardRecentTransactions;
  sales?: DashboardProductRanking;
  volume?: DashboardProductRanking;
  isLoading: boolean;
  recentError: Error | null;
  salesError: Error | null;
  volumeError: Error | null;
  onRetry: () => void;
};

export function BusinessPerformanceSection({ recent, sales, volume, isLoading, recentError, salesError, volumeError, onRetry, onNavigate }: BusinessPerformanceProps) {
  return <div className="business-performance-grid">
    <RecentTransactions data={recent} isLoading={isLoading} error={recentError} onRetry={onRetry} onNavigate={onNavigate} />
    <div className="top-products-split">
      <TopProductsBySales data={sales} isLoading={isLoading} error={salesError} onRetry={onRetry} onNavigate={onNavigate} />
      <TopProductsByVolume data={volume} isLoading={isLoading} error={volumeError} onRetry={onRetry} onNavigate={onNavigate} />
    </div>
  </div>;
}

export function RecentTransactions({ data, isLoading, error, onRetry, onNavigate }: { data?: DashboardRecentTransactions; isLoading: boolean; error: Error | null } & PanelProps & { onRetry: () => void }) {
  return <article className="dashboard-panel foundation-table-panel business-performance-panel">
    <PanelTitle title="Recent transactions" icon={ReceiptText} action={data?.items.length ? 'View all' : undefined} onAction={data?.items[0]?.view_all_path ? () => onNavigate(data.items[0].view_all_path!) : undefined} />
    {isLoading ? <PanelLoadingSkeleton /> : error ? <PanelErrorState error={error} onRetry={onRetry} /> : !data?.available ? <PanelEmptyState message={data?.reason ?? 'Transaction data is unavailable.'} /> : data.items.length === 0 ? <PanelEmptyState message="No posted transactions in this period." /> : <div className="transaction-list">{data.items.map((item) => <TransactionListItem key={item.id} item={item} timezone={data.filters.timezone} onNavigate={onNavigate} />)}</div>}
  </article>;
}

export function TransactionListItem({ item, timezone, onNavigate }: { item: DashboardTransaction; timezone?: string; onNavigate: (path: string) => void }) {
  const DirectionIcon = item.direction === 'outflow' ? ArrowDownLeft : item.direction === 'inflow' ? ArrowUpRight : FileText;
  return <div className="transaction-list-item">
    <span className={'transaction-type-icon transaction-' + item.direction}><DirectionIcon size={15} aria-hidden="true" /></span>
    <div className="transaction-main"><strong>{item.reference}</strong><span>{item.type_label} · {item.party_name}</span><time dateTime={item.occurred_at}>{formatTransactionDate(item.occurred_at, timezone)}</time></div>
    <div className="transaction-amount"><strong>{item.currency_code ? formatMoney(item.amount, item.currency_code) : '—'}</strong><span className="table-status">{item.status}</span></div>
    {item.detail_path ? <button className="icon-action" aria-label={'View details for ' + item.reference} onClick={() => onNavigate(item.detail_path!)}><ExternalLink size={14} /></button> : null}
  </div>;
}

export function TopProductsBySales({ data, isLoading, error, onRetry, onNavigate }: { data?: DashboardProductRanking; isLoading: boolean; error: Error | null } & PanelProps & { onRetry: () => void }) {
  return <ProductRankingPanel title="Top products by sales" icon={BarChart3} data={data} isLoading={isLoading} error={error} onRetry={onRetry} onNavigate={onNavigate} value={(item) => item.currency_code ? formatMoney(item.revenue, item.currency_code) : '—'} secondary={(item) => item.percentage ? formatNumber(item.percentage) + '% contribution' : 'Contribution unavailable'} />;
}

export function TopProductsByVolume({ data, isLoading, error, onRetry, onNavigate }: { data?: DashboardProductRanking; isLoading: boolean; error: Error | null } & PanelProps & { onRetry: () => void }) {
  return <ProductRankingPanel title="Top products by volume" icon={Boxes} data={data} isLoading={isLoading} error={error} onRetry={onRetry} onNavigate={onNavigate} value={(item) => formatNumber(item.quantity) + ' ' + item.unit_name} secondary={(item) => item.percentage ? formatNumber(item.percentage) + '% of volume' : 'Volume share unavailable'} />;
}

function ProductRankingPanel({ title, icon: Icon, data, isLoading, error, onRetry, onNavigate, value, secondary }: { title: string; icon: LucideIcon; data?: DashboardProductRanking; isLoading: boolean; error: Error | null; onRetry: () => void; onNavigate: (path: string) => void; value: (item: DashboardProductRanking['items'][number]) => string; secondary: (item: DashboardProductRanking['items'][number]) => string }) {
  return <article className="dashboard-panel foundation-table-panel business-performance-panel">
    <PanelTitle title={title} icon={Icon} action={data?.items.length ? 'View all' : undefined} onAction={data?.items[0]?.detail_path ? () => onNavigate(data.items[0].detail_path!) : undefined} />
    {isLoading ? <PanelLoadingSkeleton /> : error ? <PanelErrorState error={error} onRetry={onRetry} /> : !data?.available ? <PanelEmptyState message={data?.reason ?? 'Product ranking is unavailable.'} /> : data.items.length === 0 ? <PanelEmptyState message="No product sales in this period." /> : <div className="product-ranking-list">{data.items.map((item) => <div className="product-ranking-item" key={item.variant_id}><span className="product-rank">{item.rank}</span><span className="product-placeholder" aria-hidden="true"><Boxes size={15} /></span><div className="product-ranking-main"><strong>{item.product}</strong><span>{item.sku} · {item.unit_name}</span><div className="ranking-bar" aria-hidden="true"><span style={{ width: item.bar_percent + '%' }} /></div></div><div className="product-ranking-value"><strong>{value(item)}</strong><span>{secondary(item)}</span></div></div>)}</div>}
    {data?.methodology && data.items.length > 0 ? <p className="panel-footnote">{data.methodology}</p> : null}
  </article>;
}

export function PanelLoadingSkeleton() { return <div className="panel-loading-skeleton" role="status" aria-label="Loading panel">{Array.from({ length: 4 }, (_, index) => <span key={index} />)}</div>; }
export function PanelEmptyState({ message }: { message: string }) { return <div className="panel-empty-state" role="status"><span>{message}</span></div>; }
export function PanelErrorState({ error, onRetry }: { error: Error; onRetry: () => void }) { return <div className="panel-empty-state panel-error-state" role="alert"><span>{error.message || 'Unable to load this panel.'}</span><button className="table-action" onClick={onRetry}><RefreshCw size={13} />Retry</button></div>; }

function PanelTitle({ title, icon: Icon, action, onAction }: { title: string; icon: LucideIcon; action?: string; onAction?: () => void }) { return <div className="panel-heading"><div className="foundation-title"><span className="panel-icon"><Icon size={15} /></span><h3>{title}</h3></div>{action && onAction ? <button className="table-action" onClick={onAction}>{action}</button> : null}</div>; }
function formatTransactionDate(value: string, timezone?: string) { try { return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: timezone || undefined }).format(new Date(value)); } catch { return '—'; } }
