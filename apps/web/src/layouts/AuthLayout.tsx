import { Outlet } from 'react-router-dom';
import { Brand } from '../components/Brand';

export function AuthLayout() {
  return <div className="auth-shell"><div className="auth-brand"><Brand /><span className="auth-secure">Secure workspace access</span></div><main className="auth-main"><Outlet /></main><p className="auth-footer">© {new Date().getFullYear()} ERP Cloud · Secure enterprise operations</p></div>;
}
