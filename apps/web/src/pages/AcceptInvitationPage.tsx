import { useEffect, useRef } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { acceptInvitation, setActiveTenantId } from '../lib/api';
import { useAuth } from '../lib/auth';

export function AcceptInvitationPage() {
  const { user, isLoading } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const invitationId = params.get('invitation') ?? params.get('invitation_id') ?? '';
  const token = params.get('token') ?? '';
  const started = useRef(false);
  const mutation = useMutation({
    mutationFn: () => acceptInvitation(invitationId, token),
    onSuccess: (result) => { setActiveTenantId(result.tenant_id); navigate(`/?tenant_id=${encodeURIComponent(result.tenant_id)}`, { replace: true }); },
  });

  useEffect(() => {
    if (user?.email_verified_at && invitationId && token && !started.current) { started.current = true; mutation.mutate(); }
  }, [invitationId, mutation, token, user]);

  if (isLoading) return <section className="auth-page"><div className="auth-card"><p className="muted">Checking your session…</p></div></section>;
  if (!user) return <Navigate to="/login" replace state={{ from: `/accept-invitation?${params.toString()}` }} />;
  if (!user.email_verified_at) return <section className="auth-page"><div className="auth-card"><p className="eyebrow">Workspace invitation</p><h1>Verify your email first</h1><p className="muted">For your security, verify this account before accepting a workspace invitation.</p><a className="primary inline-action" href="/verify-email">Verify email</a></div></section>;

  return <section className="auth-page"><div className="auth-card">
    <p className="eyebrow">Workspace invitation</p><h1>{mutation.isPending ? 'Accepting invitation…' : mutation.isSuccess ? 'Invitation accepted' : 'Invitation could not be accepted'}</h1>
    {mutation.isPending && <p className="muted">Verifying the invitation against your authenticated account.</p>}
    {mutation.isSuccess && <p className="muted">Your workspace access is ready. Redirecting to the company dashboard.</p>}
    {!invitationId || !token ? <p className="error-text">This invitation link is incomplete. Request a new invitation from the workspace administrator.</p> : mutation.isError && <p className="error-text" role="alert">{mutation.error.message}</p>}
    {mutation.isError && <button className="primary" onClick={() => mutation.mutate()} disabled={mutation.isPending}>Try again</button>}
  </div></section>;
}
