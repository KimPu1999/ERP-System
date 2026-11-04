import ReactECharts from 'echarts-for-react';
import type { EChartsOption } from 'echarts';
import { AlertTriangle, BarChart3, Building2, CheckCircle2, Clock3, FileBarChart2, RefreshCw, ShieldCheck, Users, WalletCards } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { getPlatformSummary, type PlatformOverviewParams, type PlatformSummary } from '../lib/api';
import { useI18n, type TranslationKey } from '../lib/i18n';

type Period = NonNullable<PlatformOverviewParams['period']>;
type CardKey = 'total_businesses' | 'active_businesses' | 'pending_approvals' | 'total_users' | 'active_subscriptions' | 'system_alerts';

const cards: Array<{ key: CardKey; label: TranslationKey; icon: typeof Building2; to?: string }> = [
  { key: 'total_businesses', label: 'platform.totalBusinesses', icon: Building2, to: '/platform/tenants' },
  { key: 'active_businesses', label: 'platform.activeBusinesses', icon: ShieldCheck, to: '/platform/tenants?status=active' },
  { key: 'pending_approvals', label: 'platform.pendingApprovals', icon: Clock3, to: '/platform/tenants?status=pending' },
  { key: 'total_users', label: 'platform.totalUsers', icon: Users, to: '/platform/users' },
  { key: 'active_subscriptions', label: 'platform.activeSubscriptions', icon: WalletCards, to: '/platform/control' },
  { key: 'system_alerts', label: 'platform.systemAlerts', icon: AlertTriangle, to: '/platform/monitoring' },
];

export function PlatformDashboard() {
  const { t, formatDate, formatNumber } = useI18n();
  const [period, setPeriod] = useState<Period>('monthly');
  const [granularity, setGranularity] = useState<NonNullable<PlatformOverviewParams['granularity']>>('monthly');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const params: PlatformOverviewParams = period === 'custom' ? { period, granularity, from: from || undefined, to: to || undefined } : { period };
  const summary = useQuery({
    queryKey: ['platform-summary', params],
    queryFn: () => getPlatformSummary(params),
    enabled: period !== 'custom' || Boolean(from && to),
    staleTime: 30_000,
    placeholderData: (previous) => previous,
  });
  const values = summary.data?.summary;
  const alerts = values?.system_alerts;
  const growth = values?.business_growth ?? [];
  const statusSnapshot = values?.business_status_snapshot ?? [];
  const subscriptionStates = values?.subscription_states ?? [];
  const subscriptionAnalytics = values?.subscription_analytics ?? { available: false, reason: t('platform.subscriptionUnavailable') };
  const reportingTimezone = values?.reporting_timezone ?? 'UTC';
  const chartOption = useGrowthChart(growth, t('platform.newBusinesses'));
  const errorMessage = summary.error instanceof Error ? summary.error.message : t('platform.unableLoadOverview');
  const resetFilters = () => { setPeriod('monthly'); setGranularity('monthly'); setFrom(''); setTo(''); };

  return <section className="platform-page page-section">
    <div className="platform-toolbar platform-overview-toolbar"><div className="platform-overview-heading"><p className="eyebrow">{t('app.platform')}</p><h1>{t('nav.overview')}</h1></div><div className="platform-actions platform-overview-header-actions"><div className="platform-global-kpi-context"><span className="platform-global-kpi-badge">{t('platform.globalKpis', 'Global KPIs')}</span><span>{t('platform.globalKpisNote', 'Growth window is filterable')}</span></div>{values && <span className="platform-header-updated">{t('platform.lastUpdated')}: {formatDate(values.last_updated_at, { dateStyle: 'short', timeStyle: 'short' })}</span>}<button className="button button-secondary platform-refresh-button" type="button" onClick={() => void summary.refetch()} disabled={summary.isFetching} aria-label={t('common.refresh')} title={t('common.refresh') as string}><RefreshCw size={15} className={summary.isFetching ? 'platform-refresh-spinning' : undefined} /> <span>{summary.isFetching ? t('common.loading', 'Refreshing…') : t('common.refresh')}</span></button></div></div>
    <div className="platform-overview-filter-bar"><label><span>{t('platform.reportingPeriod')}</span><select value={period} onChange={(event) => setPeriod(event.target.value as Period)}><option value="daily">{t('platform.periodDaily')}</option><option value="weekly">{t('platform.periodWeekly')}</option><option value="monthly">{t('platform.periodMonthly')}</option><option value="yearly">{t('platform.periodYearly')}</option><option value="custom">{t('platform.periodCustom')}</option></select></label>{period === 'custom' && <><label><span>{t('platform.from')}</span><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} /></label><label><span>{t('platform.to')}</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} /></label><label><span>{t('platform.chartGranularity')}</span><select value={granularity} onChange={(event) => setGranularity(event.target.value as NonNullable<PlatformOverviewParams['granularity']>)}><option value="daily">{t('platform.periodDaily')}</option><option value="weekly">{t('platform.periodWeekly')}</option><option value="monthly">{t('platform.periodMonthly')}</option><option value="yearly">{t('platform.periodYearly')}</option></select></label></>}<button className="button button-secondary" type="button" onClick={resetFilters}>{t('platform.resetFilters')}</button>{values && <span className="platform-filter-updated">{t('platform.lastUpdated')}: {formatDate(values.last_updated_at, { dateStyle: 'short', timeStyle: 'short' })}</span>}</div>
    {summary.isError && <div className="alert alert-danger" role="alert">{errorMessage}<button className="table-action" type="button" onClick={() => void summary.refetch()}>{t('common.retry')}</button></div>}
    {period === 'custom' && (!from || !to) && <div className="panel" role="status">{t('platform.chooseDateRange')}</div>}
    {summary.isLoading && <div className="platform-kpi-grid" aria-label={t('common.loading')}>{cards.map((card) => <div className="platform-kpi platform-kpi-skeleton" key={card.key} role="status" />)}</div>}
    {!summary.isLoading && !summary.isError && !values && period !== 'custom' && <div className="panel" role="status">{t('common.noData')}</div>}
    {values && <>
      <div className="platform-kpi-grid">{cards.map(({ key, label, icon: Icon, to }) => { const value = key === 'system_alerts' ? alerts?.total ?? 0 : values[key]; return <article className="platform-kpi" key={key}><span className="platform-kpi-icon"><Icon size={16} aria-hidden="true" /></span><span>{t(label)}</span><strong aria-label={`${t(label)}: ${formatNumber(value)}`}>{formatNumber(value)}</strong>{to && <Link className="platform-kpi-link" to={to}>{t('platform.view')} →</Link>}</article>; })}</div>
      {values.monitoring && <article className="panel platform-monitoring-summary"><div className="table-card-heading"><div><h2>{t('platform.monitoringCenter')}</h2><p className="muted"><span className={`platform-health-status ${values.monitoring.overall}`} role="status"><span aria-hidden="true" />{t(`platform.status.${values.monitoring.overall}` as TranslationKey)}</span> · {values.monitoring.collector.last_run_at ? formatDate(values.monitoring.collector.last_run_at, { dateStyle: 'short', timeStyle: 'short' }) : t('platform.notObserved')}</p></div><Link className="text-link" to="/platform/monitoring">{t('platform.openMonitoring')}</Link></div><div className="platform-alert-list"><AlertRow label={t('platform.openAlerts')} value={values.monitoring.open_alerts.total} formatNumber={formatNumber} /><AlertRow label={t('platform.criticalAlerts')} value={values.monitoring.open_alerts.critical} formatNumber={formatNumber} /></div></article>}
      <div className="platform-dashboard-grid platform-overview-main-grid"><article className="panel platform-chart-panel"><div className="table-card-heading"><div><h2>{t('platform.businessGrowth')}</h2><p className="muted">{t('platform.growthDescription')}</p></div><span className="platform-data-note">{reportingTimezone}</span></div>{summary.isFetching && <div className="platform-chart-refresh" role="status">{t('common.loading')}</div>}{growth.length && growth.some((row) => row.count > 0) ? <ReactECharts option={chartOption} notMerge lazyUpdate style={{ width: '100%', height: 245 }} opts={{ renderer: 'svg' }} /> : <div className="platform-empty-state"><BarChart3 size={18} /><span>{t('platform.noRegistrationsWindow')}</span></div>}</article><article className="panel"><div className="table-card-heading"><div><h2>{t('platform.systemAlerts')}</h2><p className="muted">{t('platform.alertsDescription')}</p></div><Link className="text-link" to="/platform/monitoring">{t('platform.openMonitoring')}</Link></div><div className="platform-alert-list"><AlertRow label={t('platform.pendingBusinesses')} value={alerts?.pending_businesses ?? 0} formatNumber={formatNumber} /><AlertRow label={t('platform.pastDueSubscriptions')} value={alerts?.past_due_subscriptions ?? 0} formatNumber={formatNumber} /><AlertRow label={t('platform.failedLogins24h')} value={alerts?.failed_logins_last_24h ?? 0} formatNumber={formatNumber} /></div></article></div>
      <div className="platform-dashboard-grid"><article className="panel"><div className="table-card-heading"><h2>{t('platform.businessStatusSnapshot')}</h2></div><div className="platform-alert-list">{statusSnapshot.length ? statusSnapshot.map((row) => <AlertRow label={row.status} value={row.count} formatNumber={formatNumber} key={row.status} />) : <p className="muted">{t('platform.noAnalyticsData')}</p>}</div></article><article className="panel"><div className="table-card-heading"><div><h2>{t('platform.subscriptionStates')}</h2><p className="muted">{subscriptionAnalytics.available ? '' : subscriptionAnalytics.reason ?? t('platform.subscriptionUnavailable')}</p></div></div><div className="platform-alert-list">{subscriptionStates.length ? subscriptionStates.map((row) => <AlertRow label={row.state} value={row.count} formatNumber={formatNumber} key={row.state} />) : <p className="muted">{t('platform.noSubscriptions')}</p>}</div></article></div>
      <div className="platform-dashboard-grid"><article className="platform-table-card"><div className="table-card-heading"><h2>{t('platform.recentRegistrations')}</h2><Link className="text-link" to="/platform/tenants">{t('platform.viewAll')}</Link></div><div className="table-scroll"><table><thead><tr><th>{t('platform.businesses')}</th><th>{t('platform.ownerAdmin')}</th><th>{t('platform.created')}</th><th>{t('accounting.status')}</th><th>{t('platform.actions')}</th></tr></thead><tbody>{values.recent_registrations.length ? values.recent_registrations.map((tenant) => <tr key={tenant.id}><td><Link className="platform-entity-link" to={`/platform/tenants/${tenant.id}`} title={`${tenant.name} (${tenant.slug})`}><strong>{tenant.name}</strong></Link><small className="muted">{tenant.slug}</small></td><td>{tenant.owner ? <><strong>{tenant.owner.name}</strong><small className="muted" title={tenant.owner.email}>{tenant.owner.email}</small></> : '—'}</td><td>{formatDate(tenant.created_at)}</td><td><span className={`platform-status ${tenant.status}`}>{tenant.status}</span></td><td><Link className="button button-secondary button-small" to={`/platform/tenants/${tenant.id}`}>{t('platform.view')}</Link></td></tr>) : <tr><td colSpan={5} className="muted">{t('platform.noRegistrations')}</td></tr>}</tbody></table></div></article><article className="platform-table-card"><div className="table-card-heading"><h2>{t('platform.recentActivity')}</h2><Link className="text-link" to="/platform/audit-logs">{t('platform.viewMonitoring')}</Link></div><div className="table-scroll"><table><thead><tr><th>{t('platform.actions')}</th><th>{t('platform.actor')}</th><th>{t('platform.record')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{values.recent_activities.length ? values.recent_activities.map((activity) => <tr key={activity.id}><td><span className="platform-activity-label">{readableActivity(activity.action)}</span><small className="muted">{activity.action}</small></td><td>{activity.actor?.name ?? t('platform.system')}</td><td>{activity.resource?.type ? <span title={activity.resource.id ?? undefined}>{readableResource(activity.resource.type)}{activity.resource.id ? ` · ${shortId(activity.resource.id)}` : ''}</span> : '—'}</td><td>{formatDate(activity.created_at)}</td></tr>) : <tr><td colSpan={4} className="muted">{t('platform.noActivity')}</td></tr>}</tbody></table></div></article></div>
      <div className="panel platform-quick-actions"><div className="table-card-heading"><div><h2>{t('platform.quickActions')}</h2><p className="muted">{t('platform.quickActionsDescription')}</p></div></div><div className="platform-actions"><Link className="button button-secondary" to="/platform/tenants?create=1"><Building2 size={15} />{t('platform.addBusiness')}</Link><Link className="button button-secondary" to="/platform/tenants?status=pending"><Clock3 size={15} />{t('platform.reviewBusinesses')}</Link><Link className="button button-secondary" to="/platform/administrators"><Users size={15} />{t('platform.manageAdministrators')}</Link><Link className="button button-secondary" to="/platform/analytics"><FileBarChart2 size={15} />{t('platform.viewReports')}</Link><Link className="button button-secondary" to="/platform/monitoring"><CheckCircle2 size={15} />{t('platform.checkSystemHealth')}</Link></div></div>
    </>}
  </section>;
}

function AlertRow({ label, value, formatNumber }: { label: string; value: number; formatNumber: (value: number) => string }) {
  return <div><span>{label}</span><strong>{formatNumber(value)}</strong></div>;
}

function readableActivity(action: string): string {
  const known: Record<string, string> = {
    'business.onboarding_step_completed': 'Onboarding step completed',
    'supplier.updated': 'Supplier updated',
    'purchase_order.updated': 'Purchase order updated',
  };
  return known[action] ?? action.replace(/^(platform\.)/, '').replace(/[._-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function readableResource(type: string): string {
  return type.split('\\').pop()?.replace(/([a-z])([A-Z])/g, '$1 $2') ?? type;
}

function shortId(value: string): string {
  return value.length > 12 ? `${value.slice(0, 8)}…${value.slice(-4)}` : value;
}

function useGrowthChart(growth: PlatformSummary['business_growth'], seriesName: string): EChartsOption {
  const labels = growth.map((row) => row.label ?? row.month ?? row.bucket);
  const values = growth.map((row) => row.count);
  const styles = getComputedStyle(document.documentElement);
  const accent = styles.getPropertyValue('--blue').trim() || '#168A67';
  const muted = styles.getPropertyValue('--muted').trim() || '#64748B';
  const border = styles.getPropertyValue('--border').trim() || '#E5EBE8';
  return useMemo(() => ({
    animationDuration: 350,
    aria: { enabled: true },
    tooltip: { trigger: 'axis' },
    legend: { bottom: 0, data: [seriesName], textStyle: { color: muted, fontSize: 11 } },
    grid: { top: 18, right: 14, bottom: 38, left: 46 },
    xAxis: { type: 'category', boundaryGap: false, data: labels, axisLabel: { color: muted, fontSize: 10, hideOverlap: true } },
    yAxis: { type: 'value', minInterval: 1, axisLabel: { color: muted, fontSize: 10 }, splitLine: { lineStyle: { color: border } } },
    series: [{ name: seriesName, type: 'line', smooth: true, showSymbol: false, data: values, lineStyle: { width: 2, color: accent }, itemStyle: { color: accent }, areaStyle: { color: accent, opacity: .12 } }],
  }), [accent, border, labels, muted, seriesName, values]);
}
