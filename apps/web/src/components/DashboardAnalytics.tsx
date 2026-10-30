import ReactECharts from 'echarts-for-react';
import type { EChartsOption } from 'echarts';
import { useEffect, useMemo, useState } from 'react';
import { DashboardEmptyState } from './DashboardKpi';
import { formatMoney } from '../lib/dashboardFormatting';
import type { DashboardFinancialPerformance, DashboardGrowth, DashboardRevenueTrend } from '../lib/api';

type FinancialTab = 'income' | 'expenses' | 'profit';

export type DashboardAnalyticsProps = {
  revenue?: DashboardRevenueTrend;
  growth?: DashboardGrowth;
  financial?: DashboardFinancialPerformance;
  revenueError?: Error | null;
  growthError?: Error | null;
  financialError?: Error | null;
  isLoading: boolean;
  year: string;
  onYearChange: (year: string) => void;
  onRetry: () => void;
};

export function DashboardAnalytics({ revenue, growth, financial, revenueError, growthError, financialError, isLoading, year, onYearChange, onRetry }: DashboardAnalyticsProps) {
  return <div className="dashboard-analytics-phase3">
    <div className="analytics-period-row"><span className="analytics-period-note">Real-time authorized aggregates</span><AnalyticsPeriodSelector value={year} onChange={onYearChange} /></div>
    <div className="phase3-analytics-grid">
      <AnalyticsCard title="Revenue Overview" eyebrow="Current vs comparison period" className="phase3-revenue-card"><RevenueOverviewChart data={revenue} error={revenueError} isLoading={isLoading} onRetry={onRetry} /></AnalyticsCard>
      <AnalyticsCard title="Company Growth" eyebrow="Recognized revenue change" className="phase3-growth-card"><CompanyGrowthWidget data={growth} error={growthError} isLoading={isLoading} onRetry={onRetry} /></AnalyticsCard>
      <AnalyticsCard title="Financial Performance" eyebrow="Posted general ledger" className="phase3-financial-card"><FinancialPerformanceChart data={financial} error={financialError} isLoading={isLoading} onRetry={onRetry} /></AnalyticsCard>
    </div>
  </div>;
}

function AnalyticsCard({ title, eyebrow, className = '', children }: { title: string; eyebrow: string; className?: string; children: React.ReactNode }) {
  return <article className={`dashboard-panel foundation-panel phase3-analytics-card ${className}`}><div className="panel-heading"><div><p className="panel-eyebrow">{eyebrow}</p><h3>{title}</h3></div></div>{children}</article>;
}

function AnalyticsPeriodSelector({ value, onChange }: { value: string; onChange: (year: string) => void }) {
  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 6 }, (_, index) => String(currentYear - index));
  return <label className="analytics-period-selector">Chart period<select aria-label="Select analytics year" value={value} onChange={(event) => onChange(event.target.value)}><option value="">Selected dashboard range</option>{years.map((year) => <option value={year} key={year}>{year}</option>)}</select></label>;
}

function RevenueOverviewChart({ data, error, isLoading, onRetry }: { data?: DashboardRevenueTrend; error?: Error | null; isLoading: boolean; onRetry: () => void }) {
  const theme = useThemeVersion();
  const option = useMemo<EChartsOption | null>(() => {
    if (!data?.available || !data.points.length) return null;
    const currency = data.currency.code;
    return lineOption(data.points.map((point) => point.label), [{ name: 'Current period', values: data.points.map((point) => point.current), color: '#2563EB', area: true }, { name: 'Comparison period', values: data.points.map((point) => point.previous), color: '#94A3B8', dashed: true }], currency, theme);
  }, [data, theme]);
  return <ChartState isLoading={isLoading} error={error} available={data?.available ?? true} reason={data?.reason} hasData={Boolean(data?.points.length)} onRetry={onRetry} emptyMessage="No recognized revenue activity for this period.">{option && <ReactECharts option={option} notMerge lazyUpdate style={{ width: '100%', height: 245 }} opts={{ renderer: 'svg' }} />}</ChartState>;
}

function CompanyGrowthWidget({ data, error, isLoading, onRetry }: { data?: DashboardGrowth; error?: Error | null; isLoading: boolean; onRetry: () => void }) {
  const theme = useThemeVersion();
  const growth = data?.growth_percent === null || data?.growth_percent === undefined ? null : Number(data.growth_percent);
  const fill = growth === null ? 0 : Math.min(100, Math.max(0, growth));
  const option = useMemo<EChartsOption>(() => ({
    animationDuration: 500,
    series: [{ type: 'gauge', startAngle: 180, endAngle: 0, min: 0, max: 100, center: ['50%', '72%'], radius: '92%', pointer: { show: false }, progress: { show: true, width: 14, itemStyle: { color: '#2563EB' } }, axisLine: { lineStyle: { width: 14, color: [[1, chartColors(theme).border]] } }, axisTick: { show: false }, splitLine: { show: false }, axisLabel: { show: false }, detail: { show: false }, data: [{ value: fill }] }],
  }), [fill, theme]);
  return <ChartState isLoading={isLoading} error={error} available={data?.available ?? true} reason={data?.reason} hasData={growth !== null} onRetry={onRetry} emptyMessage="Growth is unavailable until a comparable prior period exists.">{data && growth !== null && <div className="growth-widget"><div className="growth-gauge"><ReactECharts option={option} notMerge lazyUpdate style={{ width: '100%', height: 150 }} opts={{ renderer: 'svg' }} /><strong className={growth < 0 ? 'negative' : ''}>{growth > 0 ? '+' : ''}{growth.toFixed(2)}%</strong></div><div className="growth-values"><span>Current period<strong>{formatMoney(data.current_period.value, data.currency.code)}</strong></span><span>Previous period<strong>{formatMoney(data.previous_period.value, data.currency.code)}</strong></span></div>{data.target ? <p className="analytics-note">Target progress: {data.target.percent}%</p> : <p className="analytics-note">No configured target.</p>}</div>}</ChartState>;
}

function FinancialPerformanceChart({ data, error, isLoading, onRetry }: { data?: DashboardFinancialPerformance; error?: Error | null; isLoading: boolean; onRetry: () => void }) {
  const theme = useThemeVersion();
  const [tab, setTab] = useState<FinancialTab>('income');
  const option = useMemo<EChartsOption | null>(() => {
    if (!data?.available || !data.points.length) return null;
    const labels = data.points.map((point) => point.label);
    const current = data.points.map((point) => point.current[tab]);
    const previous = data.points.map((point) => point.previous[tab]);
    return lineOption(labels, [{ name: `Current ${tab}`, values: current, color: tab === 'expenses' ? '#F59E0B' : tab === 'profit' ? '#10B981' : '#2563EB', area: true }, { name: 'Comparison period', values: previous, color: '#94A3B8', dashed: true }], data.currency.code, theme);
  }, [data, tab, theme]);
  const total = data?.totals?.current?.[tab] ?? '0';
  return <div className="financial-chart"><div className="analytics-tabs" role="tablist" aria-label="Financial performance metric">{(['income', 'expenses', 'profit'] as FinancialTab[]).map((item) => <button type="button" role="tab" aria-selected={tab === item} className={tab === item ? 'active' : ''} onClick={() => setTab(item)} key={item}>{item[0].toUpperCase() + item.slice(1)}</button>)}</div><div className="financial-total"><span>Selected period</span><strong>{data?.available ? formatMoney(total, data.currency.code) : '—'}</strong></div><ChartState isLoading={isLoading} error={error} available={data?.available ?? true} reason={data?.reason} hasData={Boolean(data?.points.length)} onRetry={onRetry} emptyMessage="No posted general-ledger activity for this period.">{option && <ReactECharts option={option} notMerge lazyUpdate style={{ width: '100%', height: 190 }} opts={{ renderer: 'svg' }} />}</ChartState></div>;
}

function lineOption(labels: string[], series: Array<{ name: string; values: string[]; color: string; area?: boolean; dashed?: boolean }>, currency: string | null, theme: string): EChartsOption {
  return { animationDuration: 450, aria: { enabled: true }, color: series.map((item) => item.color), tooltip: { trigger: 'axis', valueFormatter: (value) => formatMoney(String(value), currency) }, legend: { bottom: 0, icon: 'circle', textStyle: { color: chartColors(theme).muted, fontSize: 10 } }, grid: { top: 14, right: 12, bottom: 38, left: 48 }, xAxis: { type: 'category', boundaryGap: false, data: labels, axisLabel: { color: chartColors(theme).muted, fontSize: 9, hideOverlap: true }, axisLine: { lineStyle: { color: chartColors(theme).border } } }, yAxis: { type: 'value', axisLabel: { color: chartColors(theme).muted, fontSize: 9, formatter: (value: number) => compactNumber(value) }, splitLine: { lineStyle: { color: chartColors(theme).border } } }, series: series.map((item) => ({ name: item.name, type: 'line', smooth: true, showSymbol: false, data: item.values.map(Number), lineStyle: { width: 2, type: item.dashed ? 'dashed' : 'solid' }, areaStyle: item.area ? { opacity: .08 } : undefined })) };
}

function ChartState({ isLoading, error, available, reason, hasData, emptyMessage, onRetry, children }: { isLoading: boolean; error?: Error | null; available: boolean; reason?: string | null; hasData: boolean; emptyMessage: string; onRetry: () => void; children: React.ReactNode }) {
  if (isLoading) return <div className="chart-skeleton" role="status" aria-label="Loading chart" />;
  if (error) return <div className="chart-state" role="alert"><span>Unable to load analytics.</span><small>{error.message}</small><button type="button" className="table-action" onClick={onRetry}>Retry</button></div>;
  if (!available) return <DashboardEmptyState message={reason ?? 'This metric is unavailable.'} />;
  if (!hasData) return <DashboardEmptyState message={emptyMessage} />;
  return <>{children}</>;
}

function chartColors(theme = 'light') { const styles = getComputedStyle(document.documentElement); return { border: styles.getPropertyValue('--border').trim() || (theme === 'dark' ? '#263750' : '#E2E8F0'), muted: styles.getPropertyValue('--muted').trim() || (theme === 'dark' ? '#94A3B8' : '#64748B') }; }
function compactNumber(value: number) { return Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(value); }
function useThemeVersion() {
  const [theme, setTheme] = useState(() => document.documentElement.classList.contains('dark') ? 'dark' : 'light');
  useEffect(() => { const observer = new MutationObserver(() => setTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light')); observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] }); return () => observer.disconnect(); }, []);
  return theme;
}
