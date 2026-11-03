import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { getPlatformAnalytics } from '../lib/api';
import { useI18n } from '../lib/i18n';

function countOf(rows: Array<{ status?: string; state?: string; count: number }>, value: string): number {
  return rows.find((row) => (row.status ?? row.state) === value)?.count ?? 0;
}

export function PlatformAnalyticsPage() {
  const { t } = useI18n();
  const analytics = useQuery({ queryKey: ['platform-analytics'], queryFn: () => getPlatformAnalytics() });
  const data = analytics.data?.data;
  const maxGrowth = Math.max(...(data?.business_growth.map((row) => row.count) ?? []), 1);

  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('platform.analytics')}</p><h1>{t('platform.analyticsTitle')}</h1><p className="muted">{t('platform.analyticsDescription')}</p></div><button className="button button-secondary" type="button" onClick={() => void analytics.refetch()} disabled={analytics.isFetching}><RefreshCw size={15} /> {t('common.refresh')}</button></div>
    {analytics.isError && <div className="alert alert-danger" role="alert">{analytics.error.message}<button className="table-action" type="button" onClick={() => void analytics.refetch()}>{t('common.retry')}</button></div>}
    {analytics.isLoading && <div className="panel" role="status">{t('platform.loadingAnalytics')}</div>}
    {!analytics.isLoading && !analytics.isError && !data && <div className="panel" role="status">{t('platform.noAnalyticsData')}</div>}
    {!analytics.isLoading && data && <>
      <div className="platform-kpi-grid"><div className="platform-kpi"><span>{t('platform.activeBusinesses')}</span><strong>{countOf(data.businesses_by_status, 'active')}</strong></div><div className="platform-kpi"><span>{t('platform.pendingApprovals')}</span><strong>{countOf(data.businesses_by_status, 'pending')}</strong></div><div className="platform-kpi"><span>{t('platform.activeSubscriptions')}</span><strong>{countOf(data.subscriptions_by_state, 'active') + countOf(data.subscriptions_by_state, 'trialing')}</strong></div><div className="platform-kpi"><span>{t('platform.platformUsers')}</span><strong>{data.platform_users_by_status.reduce((total, row) => total + row.count, 0)}</strong></div></div>
      <div className="platform-dashboard-grid"><div className="panel"><div className="table-card-heading"><div><h2>{t('platform.businessGrowth')}</h2><p className="muted">{t('platform.analyticsWindow')}</p></div></div><div className="platform-growth-list">{data.business_growth.map((row) => <div className="platform-growth-row" key={row.month}><span>{row.month}</span><div className="platform-growth-track"><span style={{ width: `${(row.count / maxGrowth) * 100}%` }} /></div><strong>{row.count}</strong></div>)}</div></div><div className="panel"><div className="table-card-heading"><h2>{t('platform.businessStatus')}</h2></div><div className="platform-alert-list">{data.businesses_by_status.length ? data.businesses_by_status.map((row) => <div key={row.status}><span>{row.status}</span><strong>{row.count}</strong></div>) : <p className="muted">{t('platform.noAnalyticsData')}</p>}</div></div></div>
      <div className="platform-dashboard-grid"><div className="panel"><div className="table-card-heading"><h2>{t('platform.subscriptionState')}</h2></div><div className="platform-alert-list">{data.subscriptions_by_state.length ? data.subscriptions_by_state.map((row) => <div key={row.state}><span>{row.state}</span><strong>{row.count}</strong></div>) : <p className="muted">{t('platform.noAnalyticsData')}</p>}</div></div><div className="panel"><div className="table-card-heading"><h2>{t('platform.platformUserStatus')}</h2></div><div className="platform-alert-list">{data.platform_users_by_status.length ? data.platform_users_by_status.map((row) => <div key={row.status}><span>{row.status}</span><strong>{row.count}</strong></div>) : <p className="muted">{t('platform.noAnalyticsData')}</p>}</div></div></div>
    </>}
  </section>;
}
