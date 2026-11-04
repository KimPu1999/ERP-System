import { NavLink } from 'react-router-dom';
import { AlertTriangle, ClipboardCheck, FileBarChart2, KeyRound, ListChecks, LockKeyhole, Settings2, ShieldCheck, Users, type LucideIcon } from 'lucide-react';
import { useI18n } from '../lib/i18n';

export function SecurityCenterNavigation() {
  const { t } = useI18n();
  const links: Array<[string, string, LucideIcon]> = [
    [t('platform.overview', 'Overview'), '/platform/security', ShieldCheck],
    [t('platform.auditLogs'), '/platform/security/audit-logs', ClipboardCheck],
    [t('security.mode.loginActivity'), '/platform/security/login-activity', Users],
    [t('security.browserSessions'), '/platform/security/sessions', LockKeyhole],
    [t('security.mode.mfa'), '/platform/security/mfa', KeyRound],
    [t('security.mode.permissions'), '/platform/security/permissions', ListChecks],
    [t('security.mode.alerts'), '/platform/security/alerts', AlertTriangle],
    [t('security.mode.accessHistory'), '/platform/security/access-history', ClipboardCheck],
    [t('platform.report'), '/platform/security/reports', FileBarChart2],
    [t('security.mode.settings'), '/platform/security/settings', Settings2],
  ];

  return <nav className="security-center-tabs" aria-label={t('security.center')}>
    {links.map(([label, to, Icon]) => <NavLink end={to === '/platform/security'} to={to} key={to} className={({ isActive }) => isActive ? 'active' : undefined}><Icon size={16} aria-hidden="true" /><span>{label}</span></NavLink>)}
  </nav>;
}
