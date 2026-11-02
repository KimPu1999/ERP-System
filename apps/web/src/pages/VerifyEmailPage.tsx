import { useEffect, useRef } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useI18n } from '../lib/i18n';

export function VerifyEmailPage() {
  const { user, refresh } = useAuth();
  const { t } = useI18n();
  const mutation = useMutation({ mutationFn: () => api<{ status: string }>('/auth/email/resend', { method: 'POST' }) });
  const navigate = useNavigate();
  const location = useLocation();
  const destination = (location.state as { from?: string } | null)?.from ?? '/';
  const verificationStatus = new URLSearchParams(location.search).get('status');
  const verificationComplete = verificationStatus === 'verified' || verificationStatus === 'already_verified';
  const verificationFailed = verificationStatus === 'invalid';
  const refreshedAfterVerification = useRef(false);

  useEffect(() => {
    if (verificationComplete && user && !refreshedAfterVerification.current) {
      refreshedAfterVerification.current = true;
      void refresh();
    }
  }, [refresh, user, verificationComplete]);

  useEffect(() => {
    if (user?.email_verified_at) navigate(destination, { replace: true });
  }, [destination, navigate, user?.email_verified_at]);

  const checkStatus = async () => { await refresh(); };

  return <section className="auth-page"><div className="auth-card">
    <p className="eyebrow">{t('auth.accountSecurity')}</p>
    <h1>{verificationComplete ? t('auth.verificationSuccess') : verificationFailed ? t('auth.verificationInvalid') : t('auth.verifyEmailTitle')}</h1>
    <p className="muted">{verificationComplete ? t('auth.verificationAlreadyComplete') : verificationFailed ? t('auth.verificationInvalidDetail') : t('auth.verifyEmailDescription')}</p>
    {!verificationComplete && user && <>
      <button className="primary" type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>{mutation.isPending ? t('auth.sendingVerification') : t('auth.resendVerification')}</button>
      <button className="secondary" type="button" onClick={() => { void checkStatus(); }}>{t('auth.iVerified')}</button>
      {mutation.isSuccess && <p role="status">{t('auth.verificationEmailSent')}</p>}
      {mutation.isError && <p className="error-text" role="alert">{mutation.error.message}</p>}
    </>}
    {!user && <Link className="primary full-width" to="/login">{t('auth.backToSignIn')}</Link>}
    {verificationComplete && <Link className="primary full-width" to={user ? destination : '/login'}>{t(user ? 'auth.continue' : 'auth.backToSignIn')}</Link>}
    <span className="sr-only">{t('auth.verificationContinueHint').replace('{{destination}}', destination)}</span>
  </div></section>;
}
