import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getPlatformSecurityOverview } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function PlatformSecurityPage() {
  const { t, formatDate } = useI18n();
  const overview = useQuery({ queryKey: ['platform-security-overview'], queryFn: getPlatformSecurityOverview });
  const data = overview.data?.data;
  const metrics = data?.metrics;
  const links = [
    [t('platform.auditLogs'), '/platform/security/audit-logs'], [t('security.mode.loginActivity'), '/platform/security/login-activity'],
    [t('security.browserSessions'), '/platform/security/sessions'], [t('security.mode.mfa'), '/platform/security/mfa'],
    [t('security.mode.permissions'), '/platform/security/permissions'], [t('security.mode.alerts'), '/platform/security/alerts'],
    [t('security.mode.accessHistory'), '/platform/security/access-history'], [t('platform.report'), '/platform/security/reports'],
    [t('security.mode.settings'), '/platform/security/settings'],
  ];
  const metricCards = metrics ? [
    [t('security.failedLogins'), metrics.failed_login_attempts_24h], [t('security.activeSessions'), metrics.active_sessions],
    [t('security.mfaCoverage'), metrics.mfa_enrollment.coverage === null ? t('security.unknown') : `${metrics.mfa_enrollment.coverage}%`],
    [t('security.permissionChanges'), metrics.permission_changes_24h], [t('security.openAlerts'), metrics.open_security_alerts],
  ] : [];
  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('platform.security')}</p><h1>{t('security.pageTitle')}</h1><p className="muted">{t('security.securityMetricsNote')}</p></div><button className="button button-secondary" type="button" onClick={() => void overview.refetch()} disabled={overview.isFetching}>{t('common.refresh')}</button></div>
    {overview.isError && <div className="alert alert-danger" role="alert">{overview.error.message}<button className="table-action" type="button" onClick={() => void overview.refetch()}>{t('common.retry')}</button></div>}
    {overview.isLoading && <div className="panel" role="status">{t('common.loading')}</div>}
    {data && <>
      <div className="panel"><div className="table-card-heading"><div><p className="eyebrow">{t('security.posture')}</p><h2>{data.posture === 'protected' ? t('security.protected') : data.posture === 'attention_required' ? t('security.attentionRequired') : t('security.unknown')}</h2></div><span className={`platform-status ${data.posture}`}>{data.monitoring_available ? t('security.liveData') : t('security.monitoringUnavailable')}</span></div><p className="muted">{data.posture_basis.mfa_coverage}</p></div>
      <div className="platform-kpi-grid">{metricCards.map(([label, value]) => <article className="panel platform-kpi-card" key={String(label)}><span className="muted">{label}</span><strong>{value}</strong></article>)}</div>
      <nav className="platform-actions" aria-label={t('security.pageTitle')}>{links.map(([label, to]) => <Link className="button button-secondary" to={to} key={to}>{label}</Link>)}</nav>
      <div className="platform-table-card"><div className="table-card-heading"><h2>{t('security.recentEvents')}</h2><Link className="text-link" to="/platform/security/login-activity">{t('security.viewAll')}</Link></div><div className="table-scroll"><table><thead><tr><th>{t('security.event')}</th><th>{t('security.account')}</th><th>{t('security.result')}</th><th>{t('security.time')}</th></tr></thead><tbody>{data.recent_events.length ? data.recent_events.map((event) => <tr key={event.id}><td><strong>{event.event}</strong></td><td>{event.user?.email ?? t('security.system')}</td><td><span className={`platform-status ${event.result}`}>{event.result}</span></td><td>{formatDate(event.created_at)}</td></tr>) : <tr><td colSpan={4} className="muted">{t('security.noEvents')}</td></tr>}</tbody></table></div></div>
    </>}
  </section>;
}
