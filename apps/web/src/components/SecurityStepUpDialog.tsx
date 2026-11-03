import { useState, type FormEvent } from 'react';
import { submitSecurityStepUp } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function SecurityStepUpDialog({ onVerified, onCancel }: { onVerified: () => Promise<void> | void; onCancel: () => void }) {
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const { t } = useI18n();
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError(''); setSubmitting(true);
    try { await submitSecurityStepUp(password, code || undefined); await onVerified(); onCancel(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : t('security.stepUpTitle')); }
    finally { setSubmitting(false); }
  };
  return <div className="modal-backdrop" role="presentation"><div className="modal" role="dialog" aria-modal="true" aria-labelledby="step-up-title"><div className="modal-header"><h2 id="step-up-title">{t('security.stepUpTitle')}</h2><button className="icon-button" type="button" onClick={onCancel} aria-label={t('security.cancel')}>×</button></div><p className="muted">{t('security.stepUpDescription')}</p>{error && <div className="alert alert-danger" role="alert">{error}</div>}<form className="form" onSubmit={submit}><label>{t('security.password')}<input type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required /></label><label>{t('security.authenticatorCode')} <span className="muted">(required when MFA is enabled)</span><input inputMode="numeric" maxLength={6} value={code} onChange={(event) => setCode(event.target.value)} /></label><div className="modal-actions"><button className="button button-secondary" type="button" onClick={onCancel}>{t('security.cancel')}</button><button className="primary" type="submit" disabled={submitting}>{submitting ? t('security.verifying') : t('security.verifyContinue')}</button></div></form></div></div>;
}
