import { useQuery } from '@tanstack/react-query';
import { Building2, ShieldCheck, Users, WalletCards } from 'lucide-react';
import { getPlatformSummary } from '../lib/api';
import { useI18n } from '../lib/i18n';

const cards = [
  ['total_businesses', 'platform.businesses', Building2],
  ['active_businesses', 'platform.active', ShieldCheck],
  ['pending_approvals', 'platform.pending', Building2],
  ['suspended_businesses', 'platform.suspended', Building2],
  ['business_administrators', 'platform.administrators', Users],
  ['pending_invitations', 'platform.inviteAdministrator', Users],
  ['enabled_modules', 'nav.optionalModules', WalletCards],
] as const;

export function PlatformDashboard() {
  const { t } = useI18n();
  const summary = useQuery({ queryKey: ['platform-summary'], queryFn: getPlatformSummary });
  const values = summary.data?.summary;
  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('app.platform')}</p><h1>{t('nav.overview')}</h1></div><a className="button button-secondary" href="/platform/tenants">{t('platform.businesses')} →</a></div>
    {summary.isError && <div className="alert alert-danger" role="alert">{summary.error.message}</div>}
    <div className="platform-kpi-grid">{cards.map(([key, label, Icon]) => <div className="platform-kpi" key={key}><span className="platform-kpi-icon"><Icon size={16} /></span><span>{t(label)}</span><strong>{values?.[key] ?? '—'}</strong></div>)}</div>
    <div className="platform-dashboard-grid"><div className="panel"><div className="table-card-heading"><div><h2>{t('platform.businesses')}</h2><p className="muted">{summary.isLoading ? t('common.loading') : `${values?.total_businesses ?? 0} businesses in the platform scope`}</p></div><a className="text-link" href="/platform/tenants">{t('common.edit')} →</a></div></div><div className="panel"><div className="table-card-heading"><div><h2>{t('platform.administrators')}</h2><p className="muted">Manage invitations, roles and access without exposing authentication secrets.</p></div><a className="text-link" href="/platform/administrators">{t('platform.administrators')} →</a></div></div></div>
  </section>;
}
