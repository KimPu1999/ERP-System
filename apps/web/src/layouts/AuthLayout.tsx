import { Outlet } from 'react-router-dom';
import { Brand } from '../components/Brand';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { useI18n } from '../lib/i18n';

export function AuthLayout() {
  const { t } = useI18n();
  return <div className="auth-shell"><div className="auth-brand"><Brand /><div className="auth-brand-actions"><span className="auth-secure">{t('auth.secureAccess')}</span><LanguageSwitcher /></div></div><main className="auth-main"><Outlet /></main><p className="auth-footer">© {new Date().getFullYear()} ERP Cloud · {t('auth.footer')}</p></div>;
}
