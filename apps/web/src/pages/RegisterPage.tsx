import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { register as registerUser } from '../lib/api';
import { useI18n } from '../lib/i18n';
type Form = { name: string; email: string; password: string; password_confirmation: string };
export function RegisterPage() {
  const { register, handleSubmit, watch, formState: { errors, isSubmitting } } = useForm<Form>();
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const navigate = useNavigate();
  const { t } = useI18n();
  const password = watch('password');
  const submit = async (data: Form) => { setSubmitError(''); try { await registerUser(data); navigate('/verify-email'); } catch (error) { setSubmitError(error instanceof Error ? error.message : t('auth.unableRegister')); } };
  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">{t('auth.createAccountEyebrow')}</p><h1>{t('auth.register')}</h1>{submitError && <div className="alert alert-danger" role="alert">{submitError}</div>}<form className="form" onSubmit={handleSubmit(submit)} noValidate><label>{t('auth.name')}<input {...register('name', { required: t('auth.enterName') })} /></label>{errors.name && <small className="field-error">{errors.name.message}</small>}<label>{t('auth.email')}<input type="email" {...register('email', { required: t('auth.enterEmail'), pattern: { value: /^\S+@\S+\.\S+$/, message: t('auth.validEmail') } })} /></label>{errors.email && <small className="field-error">{errors.email.message}</small>}<label>{t('auth.password')}<div className="input-wrap"><input type={showPassword ? 'text' : 'password'} autoComplete="new-password" {...register('password', { required: t('auth.enterPassword'), minLength: { value: 8, message: t('auth.passwordLength') } })} /><button type="button" className="input-action" aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')} onClick={() => setShowPassword(!showPassword)}>{showPassword ? <EyeOff size={17} /> : <Eye size={17} />}</button></div></label>{errors.password && <small className="field-error">{errors.password.message}</small>}<label>{t('auth.confirmPassword')}<div className="input-wrap"><input type={showConfirmation ? 'text' : 'password'} autoComplete="new-password" {...register('password_confirmation', { required: t('auth.confirmPasswordError'), validate: (value) => value === password || t('auth.passwordMismatch') })} /><button type="button" className="input-action" aria-label={showConfirmation ? t('auth.hidePassword') : t('auth.showPassword')} onClick={() => setShowConfirmation(!showConfirmation)}>{showConfirmation ? <EyeOff size={17} /> : <Eye size={17} />}</button></div></label>{errors.password_confirmation && <small className="field-error">{errors.password_confirmation.message}</small>}<button className="primary" disabled={isSubmitting}>{isSubmitting ? t('auth.creatingAccount') : t('auth.createAccount')}</button></form></div></section>;
}
