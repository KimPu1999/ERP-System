import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getBusinessSelfService, updateBusinessAccount } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function BusinessSettingsPage() {
  const { t } = useI18n();
  const portal = useQuery({ queryKey: ['business-self-service'], queryFn: getBusinessSelfService });
  const data = portal.data?.data;
  const [name, setName] = useState('');
  const update = useMutation({ mutationFn: () => updateBusinessAccount({ name: name.trim() }), onSuccess: () => void portal.refetch() });
  useEffect(() => { if (data?.account.name) setName(data.account.name); }, [data?.account.name]);

  if (portal.isLoading) return <section className="page-section"><div className="panel" role="status">{t('common.loading')}</div></section>;
  if (portal.isError || !data) return <section className="page-section"><div className="alert alert-danger" role="alert">{t('business.accountError' as never, 'Unable to load account settings.')}</div></section>;
  return <section className="page-section" aria-labelledby="business-settings-title">
    <div className="page-header"><div><p className="eyebrow">{data.business.name}</p><h1 id="business-settings-title">{t('business.settingsTitle' as never, 'Account settings')}</h1><p className="muted">{t('business.settingsSubtitle' as never, 'Update your profile without changing tenant authorization or billing privileges.')}</p></div><Link className="button button-secondary" to="/business/account">{t('business.backToAccount' as never, 'Back to account')}</Link></div>
    <form className="panel" onSubmit={(event) => { event.preventDefault(); update.mutate(); }}><div className="platform-form-grid"><label><span>{t('business.displayName' as never, 'Display name')}</span><input value={name} onChange={(event) => setName(event.target.value)} maxLength={150} required /></label><label><span>{t('business.email' as never, 'Email')}</span><input value={data.account.email} readOnly /></label></div><p className="muted">{t('business.emailSecurityNote' as never, 'Email changes require the verified account recovery flow.')}</p>{update.isError && <div className="alert alert-danger" role="alert">{t('business.saveAccountError' as never, 'Account changes could not be saved.')}</div>}<button className="button button-primary" type="submit" disabled={update.isPending || !name.trim() || name.trim() === data.account.name}>{update.isPending ? t('business.saving' as never, 'Saving…') : t('business.saveAccount' as never, 'Save account')}</button></form>
  </section>;
}
