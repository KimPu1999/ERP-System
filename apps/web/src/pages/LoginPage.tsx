import { Eye, EyeOff, LockKeyhole, Mail } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { ApiError, login } from '../lib/api';
import { useAuth } from '../lib/auth';

type Form = { email: string; password: string; remember: boolean };

export function LoginPage() {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<Form>({ defaultValues: { remember: false } });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const { refresh } = useAuth();
  const submit = async (data: Form) => {
    setError('');
    try { const result = await login(data); if (result.mfa_required) navigate('/verify-mfa'); else { await refresh(); navigate('/'); } }
    catch (exception) { setError(exception instanceof ApiError && exception.status === 422 ? 'The email or password is incorrect.' : exception instanceof Error ? exception.message : 'Unable to sign in.'); }
  };
  return <section className="auth-page"><div className="auth-card"><div className="auth-card-heading"><div className="auth-icon"><LockKeyhole size={21} /></div><p className="eyebrow">Welcome back</p><h1>Sign in to ERP Cloud</h1><p className="muted">Manage your business operations from one secure workspace.</p></div>{error && <div className="alert alert-danger" role="alert">{error}</div>}<form className="form" onSubmit={handleSubmit(submit)} noValidate><div className="field"><label htmlFor="email">Email address</label><div className="input-wrap"><Mail size={17} /><input id="email" type="email" autoComplete="email" placeholder="you@company.com" aria-invalid={Boolean(errors.email)} {...register('email', { required: 'Enter your email address.' })} /></div>{errors.email && <small className="field-error">{errors.email.message}</small>}</div><div className="field"><label htmlFor="password">Password</label><div className="input-wrap"><LockKeyhole size={17} /><input id="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" placeholder="Enter your password" aria-invalid={Boolean(errors.password)} {...register('password', { required: 'Enter your password.' })} /><button type="button" className="input-action" aria-label={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(!showPassword)}>{showPassword ? <EyeOff size={17} /> : <Eye size={17} />}</button></div>{errors.password && <small className="field-error">{errors.password.message}</small>}</div><div className="form-options"><label className="checkbox"><input type="checkbox" {...register('remember')} /> <span>Remember me</span></label><Link className="text-link" to="/reset-password">Forgot password?</Link></div><button className="primary full-width" type="submit" disabled={isSubmitting}>{isSubmitting ? 'Signing in…' : 'Sign in'}</button></form><p className="auth-switch">New to ERP Cloud? <Link className="text-link" to="/register">Create an account</Link></p></div></section>;
}
