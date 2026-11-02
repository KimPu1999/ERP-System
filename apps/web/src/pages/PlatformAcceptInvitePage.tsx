import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { acceptPlatformAdminInvitation } from '../lib/api';

export function PlatformAcceptInvitePage() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const token = params.get('token') ?? '';
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const accept = useMutation({ mutationFn: () => acceptPlatformAdminInvitation({ token, name, password, password_confirmation: confirmation }), onSuccess: () => navigate('/platform/login', { replace: true }) });

  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">Platform Control Center</p><h1>Accept administrator invitation</h1><p className="muted">Create your administrator account using the secure invitation link.</p>{accept.error && <div className="alert alert-danger" role="alert">{accept.error instanceof Error ? accept.error.message : 'Unable to accept invitation.'}</div>}<form className="form" onSubmit={(event) => { event.preventDefault(); accept.mutate(); }}><label>Name<input autoFocus required value={name} onChange={(event) => setName(event.target.value)} autoComplete="name" /></label><label>Password<input required minLength={8} type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="new-password" /></label><label>Confirm password<input required minLength={8} type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="new-password" /></label><button className="primary" type="submit" disabled={accept.isPending || !token}>{accept.isPending ? 'Creating account…' : 'Create administrator account'}</button></form><p className="auth-switch"><Link className="text-link" to="/platform/login">Back to platform sign in</Link></p></div></section>;
}
