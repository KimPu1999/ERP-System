import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Activity, AlertTriangle, KeyRound, RefreshCw, ShieldCheck, type LucideIcon } from 'lucide-react';
import { SecurityCenterNavigation } from '../components/SecurityCenterNavigation';
import { getPlatformSecurityOverview } from '../lib/api';
import { useI18n } from '../lib/i18n';

const eventLabels: Record<string, string> = {
  'auth.login_failed': 'Login failed',
  'auth.login_blocked': 'Login blocked',
  'auth.mfa_enrollment_required': 'MFA enrollment required',
  'auth.mfa_enabled': 'MFA enabled',
  'auth.platform_login_succeeded': 'Platform login succeeded',
  'auth.business_login_succeeded': 'Business login succeeded',
};

function readableSecurityEvent(event: string): string {
  return eventLabels[event] ?? event.replaceAll('.', ' · ').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function PlatformSecurityPage() {
  const { t, formatDate, formatNumber } = useI18n();
  const overview = useQuery({ queryKey: ['platform-security-overview'], queryFn: getPlatformSecurityOverview });
  const data = overview.data?.data;
  const metrics = data?.metrics;
  const metricCards: Array<[string, string, LucideIcon, string]> = metrics ? [
    [t('security.failedLogins'), formatNumber(metrics.failed_login_attempts_24h), AlertTriangle, '/platform/security/login-activity'], [t('security.activeSessions'), formatNumber(metrics.active_sessions), Activity, '/platform/security/sessions'],
    [t('security.mfaCoverage'), metrics.mfa_enrollment.coverage === null ? t('security.unknown') : `${metrics.mfa_enrollment.coverage}%`, ShieldCheck, '/platform/security/mfa'],
    [t('security.permissionChanges'), formatNumber(metrics.permission_changes_24h), KeyRound, '/platform/security/permissions'], [t('security.openAlerts'), formatNumber(metrics.open_security_alerts), AlertTriangle, '/platform/security/alerts'],
  ] : [];
  return <section className="platform-page page-section">
    <div className="platform-toolbar security-center-header"><div><p className="eyebrow">{t('platform.security')}</p><h1>{t('security.pageTitle')}</h1></div><div className="platform-actions security-center-actions"><span className="security-last-updated">{t('platform.lastUpdated', 'Last updated')}: {overview.dataUpdatedAt ? formatDate(new Date(overview.dataUpdatedAt), { dateStyle: 'short', timeStyle: 'short' }) : t('platform.notObserved', 'Not observed')}</span><button className="button button-secondary security-refresh-button" type="button" onClick={() => void overview.refetch()} disabled={overview.isFetching}><RefreshCw size={15} className={overview.isFetching ? 'platform-refresh-spinning' : undefined} /><span>{overview.isFetching ? t('common.loading', 'Refreshing…') : t('common.refresh')}</span></button></div></div>
    {overview.isError && <div className="alert alert-danger" role="alert">{overview.error.message}<button className="table-action" type="button" onClick={() => void overview.refetch()}>{t('common.retry')}</button></div>}
    {overview.isLoading && <div className="panel" role="status">{t('common.loading')}</div>}
    {data && <>
      <article className="panel security-posture-card"><div className="security-posture-heading"><div className="security-section-icon"><ShieldCheck size={18} /></div><div><p className="eyebrow">{t('security.posture')}</p><h2>{data.posture === 'protected' ? t('security.protected') : data.posture === 'attention_required' ? t('security.attentionRequired') : t('security.unknown')}</h2></div><span className={`security-posture-status ${data.posture}`}>{data.monitoring_available ? t('security.liveData') : t('security.monitoringUnavailable')}</span></div><div className="security-posture-body"><div><p className="muted">{data.posture_basis.mfa_coverage}</p><div className="security-coverage-meta"><span>{t('security.mfaCoverage')}</span><strong>{metrics?.mfa_enrollment.coverage === null || metrics?.mfa_enrollment.coverage === undefined ? t('security.unknown') : `${metrics.mfa_enrollment.coverage}%`}</strong></div><div className="security-coverage-track" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={metrics?.mfa_enrollment.coverage ?? 0} aria-label={t('security.mfaCoverage')}><span style={{ width: `${metrics?.mfa_enrollment.coverage ?? 0}%` }} /></div></div><div className="security-posture-actions"><Link className="button button-secondary button-small" to="/platform/security/mfa"><ShieldCheck size={14} />{t('security.mode.mfa')}</Link><Link className="button button-secondary button-small" to="/platform/security/alerts"><AlertTriangle size={14} />{t('security.mode.alerts')}</Link></div></div></article>
      <div className="platform-kpi-grid security-kpi-grid">{metricCards.map(([label, value, Icon, to]) => <Link className="panel platform-kpi-card security-kpi-card" to={String(to)} key={String(label)}><span className="security-kpi-icon"><Icon size={16} /></span><span className="muted">{label}</span><strong>{value}</strong></Link>)}</div>
      <SecurityCenterNavigation />
      <div className="platform-table-card security-events-card security-recent-events-card"><div className="table-card-heading security-recent-events-heading"><div><p className="eyebrow">{t('security.recentEvents')}</p><h2>{t('security.recentEvents')}</h2></div><Link className="text-link" to="/platform/security/login-activity">{t('security.viewAll')}</Link></div><div className="table-scroll"><table className="security-recent-events-table"><thead><tr><th>{t('security.event')}</th><th>{t('security.account')}</th><th>{t('security.result')}</th><th>{t('security.time')}</th></tr></thead><tbody>{data.recent_events.length ? data.recent_events.map((event) => <tr key={event.id}><td><strong title={event.event}>{readableSecurityEvent(event.event)}</strong><small>{event.event}</small></td><td>{event.user?.email ?? t('security.system')}</td><td><span className={`security-result-badge ${event.result ?? 'unknown'}`}>{event.result ?? t('security.unknown')}</span></td><td>{formatDate(event.created_at)}</td></tr>) : <tr><td colSpan={4} className="muted">{t('security.noEvents')}</td></tr>}</tbody></table></div></div>
    </>}
  </section>;
}
