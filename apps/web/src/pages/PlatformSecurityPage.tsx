import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getPlatformSecurityEvents } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function PlatformSecurityPage() {
  const { t } = useI18n();
  const [search, setSearch] = useState('');
  const events = useQuery({ queryKey: ['platform-security-events', search], queryFn: () => getPlatformSecurityEvents({ search: search || undefined }) });
  const rows = events.data?.data.data ?? [];
  return <section className="platform-page page-section"><div className="platform-toolbar"><div><p className="eyebrow">{t('platform.security')}</p><h1>{t('platform.securityCenter')}</h1><p className="muted">{t('platform.securityDescription')}</p></div><button className="button button-secondary" type="button" onClick={() => void events.refetch()} disabled={events.isFetching}>{t('common.refresh')}</button></div><div className="platform-filter-bar"><label><span>{t('platform.searchEvent')}</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="auth.login_failed" /></label></div>{events.isError && <div className="alert alert-danger" role="alert">{events.error.message}<button className="table-action" type="button" onClick={() => void events.refetch()}>{t('common.retry')}</button></div>}<div className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('platform.event')}</th><th>{t('platform.user')}</th><th>{t('platform.tenant')}</th><th>{t('platform.ipAddress')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{events.isLoading && <tr><td colSpan={5}>{t('platform.loadingSecurity')}</td></tr>}{!events.isLoading && rows.map((event) => <tr key={event.id}><td><strong>{event.event}</strong></td><td>{event.user?.email ?? 'System'}</td><td>{event.tenant_id ?? 'Platform-wide'}</td><td>{event.ip_address ?? '—'}</td><td>{event.created_at ? new Date(event.created_at).toLocaleString() : '—'}</td></tr>)}{!events.isLoading && !rows.length && <tr><td colSpan={5} className="muted">{t('platform.noSecurityEvents')}</td></tr>}</tbody></table></div></div></section>;
}
