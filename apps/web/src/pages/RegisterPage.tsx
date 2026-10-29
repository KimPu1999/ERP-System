import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { register as registerUser } from '../lib/api';
type Form = { name: string; email: string; password: string; password_confirmation: string };
export function RegisterPage() {
  const { register, handleSubmit, watch, formState: { errors, isSubmitting } } = useForm<Form>();
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const navigate = useNavigate();
  const password = watch('password');
  const submit = async (data: Form) => { setSubmitError(''); try { await registerUser(data); navigate('/verify-email'); } catch (error) { setSubmitError(error instanceof Error ? error.message : 'Unable to register.'); } };
  return <section className="auth-page"><div className="auth-card"><p className="eyebrow">Create account</p><h1>Register</h1>{submitError && <div className="alert alert-danger" role="alert">{submitError}</div>}<form className="form" onSubmit={handleSubmit(submit)} noValidate><label>Name<input {...register('name', { required: 'Enter your name.' })} /></label>{errors.name && <small className="field-error">{errors.name.message}</small>}<label>Email<input type="email" {...register('email', { required: 'Enter your email.', pattern: { value: /^\S+@\S+\.\S+$/, message: 'Enter a valid email address.' } })} /></label>{errors.email && <small className="field-error">{errors.email.message}</small>}<label>Password<div className="input-wrap"><input type={showPassword ? 'text' : 'password'} autoComplete="new-password" {...register('password', { required: 'Enter a password.', minLength: { value: 8, message: 'Password must be at least 8 characters.' } })} /><button type="button" className="input-action" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(!showPassword)}>{showPassword ? <EyeOff size={17} /> : <Eye size={17} />}</button></div></label>{errors.password && <small className="field-error">{errors.password.message}</small>}<label>Confirm password<div className="input-wrap"><input type={showConfirmation ? 'text' : 'password'} autoComplete="new-password" {...register('password_confirmation', { required: 'Confirm your password.', validate: (value) => value === password || 'Passwords do not match.' })} /><button type="button" className="input-action" aria-label={showConfirmation ? 'Hide confirmation password' : 'Show confirmation password'} onClick={() => setShowConfirmation(!showConfirmation)}>{showConfirmation ? <EyeOff size={17} /> : <Eye size={17} />}</button></div></label>{errors.password_confirmation && <small className="field-error">{errors.password_confirmation.message}</small>}<button className="primary" disabled={isSubmitting}>{isSubmitting ? 'Creating account…' : 'Create account'}</button></form></div></section>;
}
