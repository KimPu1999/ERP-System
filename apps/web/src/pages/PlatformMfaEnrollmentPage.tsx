import { useEffect, useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import QRCode from 'qrcode';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useI18n } from '../lib/i18n';

export function PlatformMfaEnrollmentPage({ mode = 'platform' }: { mode?: 'platform' | 'business' }) {
  const navigate = useNavigate();
  const { user, refresh } = useAuth();
  const { t } = useI18n();
  const [enrollment, setEnrollment] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [code, setCode] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [qrCode, setQrCode] = useState<string | null>(null);
  useEffect(() => {
    if (!user || (mode === 'platform' ? !user.is_platform_super_admin : user.is_platform_super_admin)) { navigate(mode === 'platform' ? '/platform/login' : '/login', { replace: true }); return; }
    if (user.mfa_enabled_at) { navigate(mode === 'platform' ? '/platform' : '/', { replace: true }); return; }
    void api<{ secret: string; otpauth_uri: string }>('/auth/mfa/enroll', { method: 'POST', headers: { 'Content-Type': 'application/json' } }).then(setEnrollment).catch((reason: unknown) => setError(reason instanceof Error ? reason.message : t('security.mfaStartError'))).finally(() => setLoading(false));
  }, [mode, navigate, t, user]);
  useEffect(() => {
    if (!enrollment?.otpauth_uri) { setQrCode(null); return; }
    void QRCode.toDataURL(enrollment.otpauth_uri, { width: 220, margin: 1, errorCorrectionLevel: 'M' }).then(setQrCode).catch(() => setError(t('security.mfaQrError')));
  }, [enrollment, t]);
  const confirm = async (event: FormEvent) => {
    event.preventDefault(); setError('');
    try { const result = await api<{ recovery_codes: string[] }>('/auth/mfa/confirm', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ code }) }); setRecoveryCodes(result.recovery_codes); await refresh(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : t('security.mfaInvalidCode')); }
  };
  const destination = mode === 'platform' ? '/platform' : '/';
  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">{mode === 'platform' ? t('security.mfaPlatformEyebrow') : t('security.mfaBusinessEyebrow')}</p><h1>{t('security.mfaTitle')}</h1><p className="muted">{mode === 'platform' ? t('security.mfaPlatformDescription') : t('security.mfaBusinessDescription')} {t('security.mfaQrDescription')}</p>{error && <div className="alert alert-danger" role="alert">{error}</div>}{loading && <div role="status">{t('security.mfaPreparing')}</div>}{enrollment && !recoveryCodes && <><div className="mfa-enrollment-panel">{qrCode ? <img className="mfa-qr-code" src={qrCode} alt={t('security.mfaQrAlt')} /> : <div className="mfa-qr-placeholder" role="status">{t('security.mfaGeneratingQr')}</div>}<div><strong>{t('security.mfaManualSecret')}</strong><p className="mono">{enrollment.secret}</p><small className="muted">{t('security.otpauthUri')}: {enrollment.otpauth_uri}</small></div></div><form className="form" onSubmit={confirm}><label>{t('security.authenticatorCode')}<input inputMode="numeric" maxLength={6} value={code} onChange={(event) => setCode(event.target.value)} required /></label><button className="primary full-width" type="submit">{t('security.mfaConfirm')}</button></form></>}{recoveryCodes && <><div className="alert alert-warning" role="alert">{t('security.mfaSaveRecovery')}</div><pre className="panel">{recoveryCodes.join('\n')}</pre><button className="primary full-width" type="button" onClick={() => navigate(destination, { replace: true })}>{t('security.mfaContinue')}</button></>}</div></section>;
}
