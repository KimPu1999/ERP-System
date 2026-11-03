import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { getBusinessTeam } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function BusinessTeamPage() {
  const { t, formatDate } = useI18n();
  const team = useQuery({ queryKey: ['business-team'], queryFn: () => getBusinessTeam({ per_page: 50 }) });
  const data = team.data?.data;
  if (team.isLoading) return <section className="page-section"><div className="panel" role="status">{t('common.loading')}</div></section>;
  if (team.isError || !data) return <section className="page-section"><div className="alert alert-danger" role="alert">{t('business.teamError' as never, 'Unable to load team members.')}</div></section>;

  return <section className="page-section" aria-labelledby="business-team-title">
    <div className="page-header"><div><p className="eyebrow">{t('business.teamEyebrow' as never, 'Self-service')}</p><h1 id="business-team-title">{t('business.teamTitle' as never, 'Team members')}</h1><p className="muted">{t('business.teamSubtitle' as never, 'Review authorized workspace memberships and roles.')}</p></div><Link className="button button-secondary" to="/business/account">{t('business.backToAccount' as never, 'Back to account')}</Link></div>
    <div className="panel">{data.data.length === 0 ? <p className="muted">{t('business.noMembers' as never, 'No team members are available.')}</p> : <div className="table-scroll"><table><thead><tr><th>{t('business.member' as never, 'Member')}</th><th>{t('business.status' as never, 'Status')}</th><th>{t('business.roles' as never, 'Roles')}</th><th>{t('business.lastLogin' as never, 'Last login')}</th></tr></thead><tbody>{data.data.map((member) => <tr key={member.id}><td><strong>{member.name}</strong><br /><span className="muted">{member.email}</span></td><td>{member.status}</td><td>{member.roles.length ? member.roles.map((role) => role.name).join(', ') : '—'}</td><td>{member.last_login_at ? formatDate(member.last_login_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td></tr>)}</tbody></table></div>}</div>
  </section>;
}
