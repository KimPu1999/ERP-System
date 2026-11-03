import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import QRCode from 'qrcode';
import { ApiError, api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useI18n } from '../lib/i18n';

export function PlatformMfaEnrollmentPage({ mode = 'platform' }: { mode?: 'platform' | 'business' }) {
  const navigate = useNavigate();
  const { user, isLoading: authLoading, refresh, reset } = useAuth();
  const { t } = useI18n();
  const [enrollment, setEnrollment] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [code, setCode] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [qrCode, setQrCode] = useState<string | null>(null);
  const enrollmentRequestStarted = useRef(false);
  useEffect(() => {
    if (authLoading) return;
    if (!user || (mode === 'platform' ? !user.is_platform_super_admin : user.is_platform_super_admin)) { navigate(mode === 'platform' ? '/platform/login' : '/login', { replace: true }); return; }
    if (user.mfa_enabled_at) { navigate(mode === 'platform' ? '/platform' : '/', { replace: true }); return; }
    if (enrollmentRequestStarted.current) return;
    enrollmentRequestStarted.current = true;
    void api<{ secret: string; otpauth_uri: string }>('/auth/mfa/enroll', { method: 'POST', headers: { 'Content-Type': 'application/json' } }).then(setEnrollment).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : t('security.mfaStartError'))).finally(() => setLoading(false));
  }, [authLoading, mode, navigate, t, user]);
  useEffect(() => {
    if (!enrollment?.otpauth_uri) { setQrCode(null); return; }
    void QRCode.toDataURL(enrollment.otpauth_uri, { width: 220, margin: 1, errorCorrectionLevel: 'M' }).then(setQrCode).catch(() => setError(t('security.mfaQrError')));
  }, [enrollment, t]);
  const startEnrollment = () => {
    enrollmentRequestStarted.current = true;
    setLoading(true); setError(''); setEnrollment(null); setQrCode(null); setCode('');
    void api<{ secret: string; otpauth_uri: string }>('/auth/mfa/enroll', { method: 'POST', headers: { 'Content-Type': 'application/json' } })
      .then(setEnrollment).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : t('security.mfaStartError'))).finally(() => setLoading(false));
  };
  const confirm = async (event: FormEvent) => {
    event.preventDefault(); setError('');
    try { const result = await api<{ recovery_codes: string[] }>('/auth/mfa/confirm', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ code }) }); setRecoveryCodes(result.recovery_codes); await refresh(); }
    catch (reason) {
      if (reason instanceof ApiError && (reason.status === 401 || reason.status === 419)) { setEnrollment(null); setQrCode(null); setCode(''); }
      setError(reason instanceof Error ? reason.message : t('security.mfaInvalidCode'));
    }
  };
  const destination = mode === 'platform' ? '/platform' : '/';
  const signInAgain = () => { reset(); navigate(mode === 'platform' ? '/platform/login' : '/login', { replace: true }); };
  return <section className="auth-page mfa-auth-page"><div className="auth-card mfa-auth-card"><div className="mfa-page-heading"><div><p className="eyebrow">{mode === 'platform' ? t('security.mfaPlatformEyebrow') : t('security.mfaBusinessEyebrow')}</p><h1>{t('security.mfaTitle')}</h1></div><span className="mfa-required-badge">{t('security.mfaRequired', 'Required')}</span></div><p className="mfa-intro">{mode === 'platform' ? t('security.mfaPlatformDescription') : t('security.mfaBusinessDescription')} {t('security.mfaQrDescription')}</p>{error && <div className="alert alert-danger mfa-error" role="alert"><strong>{t('security.mfaActionNeeded', 'Action needed')}</strong><span>{error}</span>{(error.includes('expired') || error.includes('419')) && <button className="button button-secondary button-small" type="button" onClick={signInAgain}>{t('security.mfaSignInAgain', 'Sign in again')}</button>}</div>}{loading && <div className="mfa-loading" role="status"><span className="mfa-spinner" />{t('security.mfaPreparing')}</div>}{enrollment && !recoveryCodes && <><div className="mfa-steps" aria-label="MFA setup steps"><span className="is-active">1 <b>{t('security.mfaScanStep', 'Scan')}</b></span><i /><span>2 <b>{t('security.mfaVerifyStep', 'Verify')}</b></span></div><div className="mfa-enrollment-panel"><div className="mfa-qr-wrap">{qrCode ? <img className="mfa-qr-code" src={qrCode} alt={t('security.mfaQrAlt')} /> : <div className="mfa-qr-placeholder" role="status">{t('security.mfaGeneratingQr')}</div>}</div><div className="mfa-manual-details"><strong>{t('security.mfaManualSecret')}</strong><p className="mono mfa-secret">{enrollment.secret}</p><small className="muted mfa-uri">{t('security.otpauthUri')}: {enrollment.otpauth_uri}</small></div></div><form className="form mfa-confirm-form" onSubmit={confirm}><label>{t('security.authenticatorCode')}<input className="mfa-code-input" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]*" maxLength={6} value={code} onChange={(event) => setCode(event.target.value.replace(/\D/g, ''))} placeholder="000000" required /></label><button className="primary full-width" type="submit" disabled={code.length !== 6}>{t('security.mfaConfirm')}</button><button className="button button-secondary full-width" type="button" onClick={startEnrollment}>{t('security.mfaRestart', 'Generate a new setup')}</button></form></>}{recoveryCodes && <div className="mfa-success-state"><div className="mfa-success-icon">✓</div><h2>{t('security.mfaEnabled', 'MFA is enabled')}</h2><div className="alert alert-warning" role="alert">{t('security.mfaSaveRecovery')}</div><pre className="panel recovery-code-list">{recoveryCodes.join('\n')}</pre><button className="primary full-width" type="button" onClick={() => navigate(destination, { replace: true })}>{t('security.mfaContinue')}</button></div>}</div></section>;
}
