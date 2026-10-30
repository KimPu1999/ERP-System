import { useMutation } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { acceptSupplierPortalInvitation } from '../lib/api';

export function SupplierPortalInvitationPage() {
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const accept = useMutation({ mutationFn: () => acceptSupplierPortalInvitation(token) });
  return <section className="auth-shell"><div className="auth-card"><p className="eyebrow">ERP Cloud Supplier Portal</p><h1>Accept invitation</h1><p className="muted">Sign in with the invited email address, then accept this secure invitation.</p>{accept.isError ? <p className="error-text">This invitation is invalid, expired, revoked, or does not match the signed-in email.</p> : null}{accept.isSuccess ? <p className="success-text">Invitation accepted. You can now open the Supplier Portal.</p> : <button className="primary-button" disabled={!token || accept.isPending} onClick={() => accept.mutate()}>{accept.isPending ? 'Accepting…' : 'Accept invitation'}</button>}</div></section>;
}
