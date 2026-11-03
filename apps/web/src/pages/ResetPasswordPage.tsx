import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { requestPasswordReset, resetPassword } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function ResetPasswordPage() {
  const { t } = useI18n();
  const [params] = useSearchParams();
  const location = useLocation();
  const token = params.get('token') ?? '';
  const [email, setEmail] = useState(params.get('email') ?? '');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setMessage('');
    setError('');
    setIsSubmitting(true);
    try {
      if (token) {
        await resetPassword({ token, email, password, password_confirmation: confirmation });
        setMessage(t('auth.passwordResetComplete'));
        setPassword('');
        setConfirmation('');
      } else {
        await requestPasswordReset(email);
        setMessage(t('auth.resetLinkSent'));
      }
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : t('auth.unableResetPassword'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">{t('auth.accountRecovery')}</p><h1>{t('auth.resetPasswordTitle')}</h1><p className="muted">{token ? t('auth.resetPasswordFormDescription') : t('auth.resetPasswordDescription')}</p>{message && <div className="alert alert-success" role="status">{message}</div>}{error && <div className="alert alert-danger" role="alert">{error}</div>}{token && !email && <div className="alert alert-danger" role="alert">{t('auth.invalidResetLink')}</div>}<form className="form" onSubmit={submit}><label>{t('auth.email')}<input required type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} /></label>{token && <><label>{t('auth.newPassword')}<input required minLength={8} type="password" autoComplete="new-password" value={password} onChange={(event) => setPassword(event.target.value)} /></label><label>{t('auth.resetPasswordConfirmation')}<input required minLength={8} type="password" autoComplete="new-password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label></>}<button className="primary" type="submit" disabled={isSubmitting || (Boolean(token) && !email)}>{isSubmitting ? t('common.loading') : token ? t('auth.completePasswordReset') : t('auth.sendResetLink')}</button></form><p className="auth-switch"><Link className="text-link" to={location.pathname.startsWith('/platform/') ? '/platform/login' : '/login'}>{t('auth.backToSignIn')}</Link></p></div></section>;
}
