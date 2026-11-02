import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createPlatformAdminInvitation, getPlatformAdminInvitations, resendPlatformAdminInvitation, revokePlatformAdminInvitation } from '../lib/api';

export function PlatformAdminInvitationsPage() {
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
    <div className="platform-toolbar"><div><p className="eyebrow">Platform access</p><h1>Administrator invitations</h1></div><button className="button button-primary" type="button" onClick={() => { save.reset(); setOpen(true); }}>Invite administrator</button></div>
    {error && <div className="alert alert-danger" role="alert">{error instanceof Error ? error.message : 'Unable to complete this action.'}</div>}
    <div className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>Email</th><th>Status</th><th>Expires</th><th>Invited by</th><th>Actions</th></tr></thead><tbody>
      {invitations.isLoading && <tr><td colSpan={5}>Loading invitations…</td></tr>}
      {!invitations.isLoading && !rows.length && <tr><td colSpan={5}>No platform administrator invitations.</td></tr>}
      {rows.map((invitation) => <tr key={invitation.id}><td>{invitation.email}</td><td><span className={`platform-status ${invitation.status}`}>{invitation.status}</span></td><td>{new Date(invitation.expires_at).toLocaleString()}</td><td>{invitation.inviter?.name ?? '—'}</td><td><div className="platform-actions"><button className="button button-secondary button-small" type="button" disabled={resend.isPending || invitation.status === 'accepted' || invitation.status === 'revoked'} onClick={() => resend.mutate(invitation.id)}>Resend</button>{invitation.status === 'pending' && <button className="button button-danger button-small" type="button" disabled={revoke.isPending} onClick={() => revoke.mutate(invitation.id)}>Revoke</button>}</div></td></tr>)}
    </tbody></table></div><footer className="platform-table-footer"><span>{invitations.data?.data.total ?? 0} total</span><button className="button button-secondary button-small" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button><span>Page {invitations.data?.data.current_page ?? page} of {invitations.data?.data.last_page ?? 1}</span><button className="button button-secondary button-small" type="button" disabled={page >= (invitations.data?.data.last_page ?? 1)} onClick={() => setPage(page + 1)}>Next</button></footer></div>
    {open && <div className="platform-modal-overlay"><form className="platform-modal" onSubmit={(event) => { event.preventDefault(); save.mutate(); }}><div className="platform-modal-header"><div><p className="eyebrow">Platform access</p><h2>Invite administrator</h2></div><button className="icon-button" type="button" aria-label="Close" onClick={() => setOpen(false)}>×</button></div><label><span>Email address</span><input autoFocus required type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="admin@example.com" /></label><p className="muted">The invitation is single-use and expires after seven days. The recipient creates their own password.</p><div className="platform-modal-footer"><button className="button button-secondary" type="button" onClick={() => setOpen(false)}>Cancel</button><button className="button button-primary" type="submit" disabled={save.isPending}>{save.isPending ? 'Sending…' : 'Send invitation'}</button></div></form></div>}
  </section>;
}
