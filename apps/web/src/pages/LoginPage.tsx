import { Eye, EyeOff, LockKeyhole, Mail } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError, login } from '../lib/api';
import { useAuth } from '../lib/auth';
import { useI18n } from '../lib/i18n';

type Form = { email: string; password: string; remember: boolean };

export function LoginPage() {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<Form>({ defaultValues: { remember: false } });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const { refresh } = useAuth();
  const { t } = useI18n();
  const submit = async (data: Form) => {
    setError('');
    try { const result = await login(data); if (result.mfa_required) navigate('/verify-mfa'); else { await refresh(); navigate('/'); } }
    catch (exception) { setError(exception instanceof ApiError && exception.status === 422 ? t('auth.invalidCredentials') : exception instanceof Error ? exception.message : t('auth.unableSignIn')); }
  };
  return <section className="auth-page"><div className="auth-card"><div className="auth-card-heading"><div className="auth-icon"><LockKeyhole size={21} /></div><p className="eyebrow">{t('auth.welcome')}</p><h1>{t('auth.signInTitle')}</h1><p className="muted">{t('auth.signInDescription')}</p></div>{error && <div className="alert alert-danger" role="alert">{error}</div>}<form className="form" onSubmit={handleSubmit(submit)} noValidate><div className="field"><label htmlFor="email">{t('auth.email')}</label><div className="input-wrap"><Mail size={17} /><input id="email" type="email" autoComplete="email" placeholder={t('auth.emailPlaceholder')} aria-invalid={Boolean(errors.email)} {...register('email', { required: t('auth.enterEmail') })} /></div>{errors.email && <small className="field-error">{errors.email.message}</small>}</div><div className="field"><label htmlFor="password">{t('auth.password')}</label><div className="input-wrap"><LockKeyhole size={17} /><input id="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" placeholder={t('auth.passwordPlaceholder')} aria-invalid={Boolean(errors.password)} {...register('password', { required: t('auth.enterPassword') })} /><button type="button" className="input-action" aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')} onClick={() => setShowPassword(!showPassword)}>{showPassword ? <EyeOff size={17} /> : <Eye size={17} />}</button></div>{errors.password && <small className="field-error">{errors.password.message}</small>}</div><div className="form-options"><label className="checkbox"><input type="checkbox" {...register('remember')} /> <span>{t('auth.remember')}</span></label><Link className="text-link" to="/reset-password">{t('auth.forgot')}</Link></div><button className="primary full-width" type="submit" disabled={isSubmitting}>{isSubmitting ? t('auth.signingIn') : t('auth.signIn')}</button></form><p className="auth-switch">{t('auth.newTo')} <Link className="text-link" to="/register">{t('auth.createAccount')}</Link></p></div></section>;
}
