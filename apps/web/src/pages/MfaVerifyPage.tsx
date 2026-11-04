import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';

export function MfaVerifyPage() {
  const { register, handleSubmit, setValue, formState: { errors, isSubmitting } } = useForm<{ code: string }>();
  const navigate = useNavigate(); const location = useLocation();
  const mode = (location.state as { mode?: 'business' | 'platform' } | null)?.mode ?? 'business';
  const [error, setError] = useState('');
  const [recoveryMode, setRecoveryMode] = useState(false);
  const submit = async (data: { code: string }) => { setError(''); try { await api('/auth/mfa/verify', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }); navigate(mode === 'platform' ? '/platform' : '/', { replace: true }); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Invalid MFA code.'); } };
  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">Multi-factor authentication</p><h1>Verify sign-in</h1><p className="muted">{recoveryMode ? 'Enter one unused recovery code.' : 'Enter the current six-digit code from your authenticator app.'}</p>{error && <div className="alert alert-danger" role="alert">{error}</div>}<form className="form" onSubmit={handleSubmit(submit)}><label htmlFor="mfa-code">{recoveryMode ? 'Recovery code' : 'Authenticator code'}<input id="mfa-code" autoFocus autoComplete="one-time-code" inputMode={recoveryMode ? 'text' : 'numeric'} pattern={recoveryMode ? undefined : '[0-9]*'} maxLength={recoveryMode ? 20 : 6} {...register('code', { required: recoveryMode ? 'Enter your recovery code.' : 'Enter your six-digit code.', pattern: recoveryMode ? undefined : { value: /^\d{6}$/, message: 'Enter six digits.' } })} onChange={(event) => setValue('code', recoveryMode ? event.target.value.toUpperCase().replace(/\s/g, '') : event.target.value.replace(/\D/g, ''), { shouldValidate: true })} /></label>{errors.code && <small className="field-error">{errors.code.message}</small>}<button className="primary" disabled={isSubmitting}>{isSubmitting ? 'Verifying…' : 'Verify'}</button></form><button className="button button-secondary full-width" type="button" onClick={() => { setRecoveryMode(!recoveryMode); setError(''); setValue('code', ''); }}>{recoveryMode ? 'Use authenticator code' : 'Use recovery code'}</button><Link className="text-link" to={mode === 'platform' ? '/platform/login' : '/login'}>Back to Login</Link></div></section>;
}
