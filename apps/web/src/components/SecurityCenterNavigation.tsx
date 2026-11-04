import { NavLink } from 'react-router-dom';
import { useI18n } from '../lib/i18n';

export function SecurityCenterNavigation() {
  const { t } = useI18n();
  const links = [
    [t('platform.overview', 'Overview'), '/platform/security'],
    [t('platform.auditLogs'), '/platform/security/audit-logs'],
    [t('security.mode.loginActivity'), '/platform/security/login-activity'],
    [t('security.browserSessions'), '/platform/security/sessions'],
    [t('security.mode.mfa'), '/platform/security/mfa'],
    [t('security.mode.permissions'), '/platform/security/permissions'],
    [t('security.mode.alerts'), '/platform/security/alerts'],
    [t('security.mode.accessHistory'), '/platform/security/access-history'],
    [t('platform.report'), '/platform/security/reports'],
    [t('security.mode.settings'), '/platform/security/settings'],
  ];

  return <nav className="security-center-tabs" aria-label={t('security.center')}>
    {links.map(([label, to]) => <NavLink end={to === '/platform/security'} to={to} key={to} className={({ isActive }) => isActive ? 'active' : undefined}>{label}</NavLink>)}
  </nav>;
}
