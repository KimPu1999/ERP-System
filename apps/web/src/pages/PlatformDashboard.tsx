import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Building2, RefreshCw, ShieldCheck, Users, WalletCards } from 'lucide-react';
import { Link } from 'react-router-dom';
import { getPlatformSummary } from '../lib/api';
import { useI18n } from '../lib/i18n';

const cards = [
  ['total_businesses', 'platform.businesses', Building2],
  ['active_businesses', 'platform.activeBusinesses', ShieldCheck],
  ['pending_approvals', 'platform.pendingApprovals', Building2],
  ['total_platform_users', 'platform.platformUsers', Users],
  ['active_subscriptions', 'platform.activeSubscriptions', WalletCards],
  ['system_alerts', 'platform.systemAlerts', AlertTriangle],
] as const;

function date(value: string | null): string {
  return value ? new Date(value).toLocaleDateString() : '—';
}

export function PlatformDashboard() {
  const { t } = useI18n();
  const summary = useQuery({ queryKey: ['platform-summary'], queryFn: getPlatformSummary });
  const values = summary.data?.summary;
  const growth = values?.business_growth ?? [];
  const maxGrowth = Math.max(...growth.map((item) => item.count), 1);

  return <section className="platform-page page-section">
    <div className="platform-toolbar">
      <div><p className="eyebrow">{t('app.platform')}</p><h1>{t('nav.overview')}</h1><p className="muted">{t('platform.dashboardSubtitle')}</p></div>
      <div className="platform-actions"><button className="button button-secondary" type="button" onClick={() => void summary.refetch()} disabled={summary.isFetching}><RefreshCw size={15} /> {t('common.refresh')}</button><Link className="button button-primary" to="/platform/tenants">{t('platform.manageBusinesses')}</Link></div>
    </div>
    {summary.isError && <div className="alert alert-danger" role="alert">{summary.error.message}<button className="table-action" type="button" onClick={() => void summary.refetch()}>{t('common.retry')}</button></div>}
    {summary.isLoading && <div className="panel" role="status">{t('common.loading')}</div>}
    {!summary.isLoading && !summary.isError && !values && <div className="panel" role="status">{t('common.noData')}</div>}
    {!summary.isLoading && !summary.isError && values && <>
      <div className="platform-kpi-grid">{cards.map(([key, label, Icon]) => { const value = key === 'system_alerts' ? values.system_alerts.total : values[key]; return <div className="platform-kpi" key={key}><span className="platform-kpi-icon"><Icon size={16} /></span><span>{t(label)}</span><strong>{value}</strong></div>; })}</div>
      <div className="platform-dashboard-grid">
        <div className="panel"><div className="table-card-heading"><div><h2>{t('platform.businessGrowth')}</h2><p className="muted">{t('platform.growthDescription')}</p></div></div><div className="platform-growth-list">{growth.length ? growth.map((item) => <div className="platform-growth-row" key={item.month}><span>{item.month}</span><div className="platform-growth-track"><span style={{ width: `${(item.count / maxGrowth) * 100}%` }} /></div><strong>{item.count}</strong></div>) : <p className="muted">{t('platform.noRegistrationsWindow')}</p>}</div></div>
        <div className="panel"><div className="table-card-heading"><div><h2>{t('platform.systemAlerts')}</h2><p className="muted">{t('platform.alertsDescription')}</p></div><Link className="text-link" to="/platform/monitoring">{t('platform.openMonitoring')}</Link></div><div className="platform-alert-list"><div><span>{t('platform.pendingBusinesses')}</span><strong>{values.system_alerts.pending_businesses}</strong></div><div><span>{t('platform.pastDueSubscriptions')}</span><strong>{values.system_alerts.past_due_subscriptions}</strong></div><div><span>{t('platform.failedLogins24h')}</span><strong>{values.system_alerts.failed_logins_last_24h}</strong></div></div></div>
      </div>
      <div className="platform-dashboard-grid">
        <div className="platform-table-card"><div className="table-card-heading"><h2>{t('platform.recentRegistrations')}</h2><Link className="text-link" to="/platform/tenants">{t('platform.viewAll')}</Link></div><div className="table-scroll"><table><thead><tr><th>{t('platform.businesses')}</th><th>{t('platform.account')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{values.recent_registrations.length ? values.recent_registrations.map((tenant) => <tr key={tenant.id}><td><strong>{tenant.name}</strong><small className="muted">{tenant.slug}</small></td><td><span className={`platform-status ${tenant.status}`}>{tenant.status}</span></td><td>{date(tenant.created_at)}</td></tr>) : <tr><td colSpan={3} className="muted">{t('platform.noRegistrations')}</td></tr>}</tbody></table></div></div>
        <div className="platform-table-card"><div className="table-card-heading"><h2>{t('platform.recentActivity')}</h2><Link className="text-link" to="/platform/monitoring">{t('platform.viewMonitoring')}</Link></div><div className="table-scroll"><table><thead><tr><th>{t('platform.actions')}</th><th>{t('platform.actor')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{values.recent_activities.length ? values.recent_activities.map((activity) => <tr key={activity.id}><td>{activity.action}</td><td>{activity.actor?.email ?? 'System'}</td><td>{date(activity.created_at)}</td></tr>) : <tr><td colSpan={3} className="muted">{t('platform.noActivity')}</td></tr>}</tbody></table></div></div>
      </div>
      <div className="panel platform-quick-actions"><div className="table-card-heading"><div><h2>{t('platform.quickActions')}</h2><p className="muted">{t('platform.quickActionsDescription')}</p></div></div><div className="platform-actions"><Link className="button button-secondary" to="/platform/tenants">{t('platform.reviewBusinesses')}</Link><Link className="button button-secondary" to="/platform/invitations">{t('platform.inviteAdministrator')}</Link><Link className="button button-secondary" to="/platform/control">{t('platform.manageBilling')}</Link><Link className="button button-secondary" to="/platform/monitoring">{t('platform.checkSystemHealth')}</Link></div></div>
    </>}
  </section>;
}
