import { Link, useSearchParams } from 'react-router-dom';
import { useI18n } from '../lib/i18n';

export function AccessDeniedPage() {
  const { t } = useI18n();
  const [params] = useSearchParams();
  const suspended = params.get('reason') === 'suspended';
  const platform = params.get('reason') === 'platform';

  return <section className="auth-page"><div className="auth-card">
    <p className="eyebrow">{t('auth.accountSecurity')}</p>
    <h1>{suspended ? t('auth.suspendedTitle') : t('auth.accessDeniedTitle')}</h1>
    <p className="muted">{suspended ? t('auth.suspendedDetail') : t('auth.accessDeniedDetail')}</p>
    <div className="profile-actions">
      <Link className="primary" to={platform ? '/login' : '/business/login'}>{platform ? t('auth.businessSignIn') : t('auth.returnToBusinessSignIn')}</Link>
      {platform && <Link className="secondary" to="/platform/login">{t('auth.returnToPlatformSignIn')}</Link>}
    </div>
  </div></section>;
}
