import { useEffect, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useI18n } from '../lib/i18n';

export function VerifyEmailPage() {
  const { user, refresh } = useAuth();
  const { t } = useI18n();
  const mutation = useMutation({ mutationFn: () => api<{ status: string; message: string }>('/auth/email/resend', { method: 'POST' }) });
  const [cooldown, setCooldown] = useState(0);
  const navigate = useNavigate();
  const location = useLocation();
  const destination = (location.state as { from?: string } | null)?.from ?? '/';
  const verificationStatus = new URLSearchParams(location.search).get('status');
  const verificationMode = new URLSearchParams(location.search).get('mode');
  const signInPath = verificationMode === 'platform' ? '/platform/login' : '/login';
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
  useEffect(() => {
    if (cooldown <= 0) return;
    const timer = window.setInterval(() => setCooldown((value) => Math.max(0, value - 1)), 1000);
    return () => window.clearInterval(timer);
  }, [cooldown]);
  const resend = () => { mutation.mutate(undefined, { onSuccess: () => setCooldown(60) }); };
  const deliveryConfirmed = mutation.data?.status === 'accepted_by_configured_mailer' || mutation.data?.status === 'queued';

  return <section className="auth-page"><div className="auth-card">
    <p className="eyebrow">{t('auth.accountSecurity')}</p>
    <h1>{verificationComplete ? t('auth.verificationSuccess') : verificationFailed ? t('auth.verificationInvalid') : t('auth.verifyEmailTitle')}</h1>
    <p className="muted">{verificationComplete ? t('auth.verificationAlreadyComplete') : verificationFailed ? t('auth.verificationInvalidDetail') : t('auth.verifyEmailDescription')}</p>
    {!verificationComplete && user && <>
      <p className="muted">{user.email}</p>
      <button className="primary" type="button" disabled={mutation.isPending || cooldown > 0} onClick={resend}>{mutation.isPending ? t('auth.sendingVerification') : cooldown > 0 ? `${t('auth.resendVerification')} (${cooldown}s)` : t('auth.resendVerification')}</button>
      <button className="secondary" type="button" onClick={() => { void checkStatus(); }}>{t('auth.iVerified')}</button>
      {mutation.isSuccess && <p role="status" className={deliveryConfirmed ? 'text-success' : 'error-text'}>{mutation.data.message}</p>}
      {mutation.isError && <p className="error-text" role="alert">{mutation.error.message}</p>}
    </>}
    {!user && !verificationComplete && <>
      <Link className="primary full-width" to={signInPath}>{t('auth.backToSignIn')}</Link>
      {verificationMode !== 'platform' && <Link className="secondary full-width" to="/platform/login">{t('auth.platformSignIn')}</Link>}
    </>}
    {verificationComplete && <>
      <Link className="primary full-width" to={user ? destination : signInPath}>{t(user ? 'auth.continue' : 'auth.backToSignIn')}</Link>
      {!user && verificationMode !== 'platform' && <Link className="secondary full-width" to="/platform/login">{t('auth.platformSignIn')}</Link>}
    </>}
    <span className="sr-only">{t('auth.verificationContinueHint').replace('{{destination}}', destination)}</span>
  </div></section>;
}
