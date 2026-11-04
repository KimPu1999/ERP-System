import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { MailPlus, Send } from 'lucide-react';
import { createPlatformAdminInvitation, getPlatformAdminInvitations, resendPlatformAdminInvitation, revokePlatformAdminInvitation } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function PlatformAdminInvitationsPage() {
  const { t } = useI18n();
  const client = useQueryClient();
  const [email, setEmail] = useState('');
  const [open, setOpen] = useState(false);
  const [page, setPage] = useState(1);
  const invitations = useQuery({ queryKey: ['platform-admin-invitations', page], queryFn: () => getPlatformAdminInvitations({ page, per_page: 25 }) });
  const save = useMutation({
    mutationFn: () => createPlatformAdminInvitation({ email }),
    onSuccess: () => { setEmail(''); setOpen(false); void client.invalidateQueries({ queryKey: ['platform-admin-invitations'] }); },
  });
  const resend = useMutation({ mutationFn: resendPlatformAdminInvitation, onSuccess: () => void client.invalidateQueries({ queryKey: ['platform-admin-invitations'] }) });
  const revoke = useMutation({ mutationFn: revokePlatformAdminInvitation, onSuccess: () => void client.invalidateQueries({ queryKey: ['platform-admin-invitations'] }) });
  const rows = invitations.data?.data.data ?? [];
  const error = save.error ?? resend.error ?? revoke.error ?? invitations.error;

  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('platform.platformAccess')}</p><h1>{t('platform.administratorInvitations')}</h1></div><button className="button button-primary" type="button" onClick={() => { save.reset(); setOpen(true); }}><MailPlus size={15} aria-hidden="true" /><span>{t('platform.inviteAdministrator')}</span></button></div>
    {error && <div className="alert alert-danger" role="alert">{error instanceof Error ? error.message : 'Unable to complete this action.'}</div>}
    <div className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('platform.emailAddress')}</th><th>{t('accounting.status')}</th><th>{t('platform.expires')}</th><th>{t('platform.invitedBy')}</th><th>{t('platform.actions')}</th></tr></thead><tbody>
      {invitations.isLoading && <tr><td colSpan={5}>{t('platform.loadingInvitations')}</td></tr>}
      {!invitations.isLoading && !rows.length && <tr><td colSpan={5}>{t('platform.noAdminInvitations')}</td></tr>}
      {rows.map((invitation) => <tr key={invitation.id}><td>{invitation.email}</td><td><span className={`platform-status ${invitation.status}`}>{invitation.status}</span></td><td>{new Date(invitation.expires_at).toLocaleString()}</td><td>{invitation.inviter?.name ?? '—'}</td><td><div className="platform-actions"><button className="button button-secondary button-small" type="button" disabled={resend.isPending || invitation.status === 'accepted' || invitation.status === 'revoked'} onClick={() => resend.mutate(invitation.id)}>{t('platform.resend')}</button>{invitation.status === 'pending' && <button className="button button-danger button-small" type="button" disabled={revoke.isPending} onClick={() => revoke.mutate(invitation.id)}>{t('platform.revoke')}</button>}</div></td></tr>)}
    </tbody></table></div><footer className="platform-table-footer"><span>{invitations.data?.data.total ?? 0} total</span><button className="button button-secondary button-small" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('common.previous')}</button><span>{t('common.page')} {invitations.data?.data.current_page ?? page} {t('common.of')} {invitations.data?.data.last_page ?? 1}</span><button className="button button-secondary button-small" type="button" disabled={page >= (invitations.data?.data.last_page ?? 1)} onClick={() => setPage(page + 1)}>{t('common.next')}</button></footer></div>
    {open && <div className="platform-modal-overlay"><form className="platform-modal platform-invitation-modal" onSubmit={(event) => { event.preventDefault(); save.mutate(); }}><div className="platform-modal-header"><div><p className="eyebrow">{t('platform.platformAccess')}</p><h2>{t('platform.inviteAdministrator')}</h2></div><button className="icon-button" type="button" aria-label={t('common.close')} onClick={() => setOpen(false)}>×</button></div><label><span>{t('platform.emailAddress')}</span><input autoFocus required type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="admin@example.com" /></label><p className="muted">{t('platform.singleUseInvitation')}</p><div className="platform-modal-footer"><button className="button button-secondary" type="button" onClick={() => setOpen(false)}>{t('common.cancel')}</button><button className="button button-primary" type="submit" disabled={save.isPending}><Send size={15} aria-hidden="true" /><span>{save.isPending ? t('platform.sending') : t('platform.sendInvitation')}</span></button></div></form></div>}
  </section>;
}
