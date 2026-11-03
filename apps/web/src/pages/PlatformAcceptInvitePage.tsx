import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { acceptPlatformAdminInvitation } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function PlatformAcceptInvitePage() {
  const { t } = useI18n();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const token = params.get('token') ?? '';
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const accept = useMutation({ mutationFn: () => acceptPlatformAdminInvitation({ token, name, password, password_confirmation: confirmation }), onSuccess: () => navigate('/platform/login', { replace: true }) });

  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">{t('platform.platformAccess')}</p><h1>{t('platform.acceptAdministratorInvitation')}</h1><p className="muted">{t('platform.createAccountInvitationDetail')}</p>{accept.error && <div className="alert alert-danger" role="alert">{accept.error instanceof Error ? accept.error.message : t('platform.unableAcceptInvitation')}</div>}<form className="form" onSubmit={(event) => { event.preventDefault(); accept.mutate(); }}><label>{t('platform.name')}<input autoFocus required value={name} onChange={(event) => setName(event.target.value)} autoComplete="name" /></label><label>{t('platform.password')}<input required minLength={8} type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="new-password" /></label><label>{t('platform.confirmPassword')}<input required minLength={8} type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="new-password" /></label><button className="primary" type="submit" disabled={accept.isPending || !token}>{accept.isPending ? t('common.loading') : t('platform.createAdministratorAccount')}</button></form><p className="auth-switch"><Link className="text-link" to="/platform/login">{t('platform.backToPlatformSignIn')}</Link></p></div></section>;
}
