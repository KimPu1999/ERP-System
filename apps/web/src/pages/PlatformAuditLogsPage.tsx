import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getPlatformAuditLogs } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function PlatformAuditLogsPage() {
  const { t } = useI18n();
  const [search, setSearch] = useState('');
  const logs = useQuery({ queryKey: ['platform-audit-logs', search], queryFn: () => getPlatformAuditLogs({ search: search || undefined }) });
  const rows = logs.data?.data.data ?? [];
  return <section className="platform-page page-section"><div className="platform-toolbar"><div><p className="eyebrow">{t('platform.security')}</p><h1>{t('platform.auditLogs')}</h1><p className="muted">{t('platform.auditDescription')}</p></div><button className="button button-secondary" type="button" onClick={() => void logs.refetch()} disabled={logs.isFetching}>{t('common.refresh')}</button></div><div className="platform-filter-bar"><label><span>{t('platform.searchAction')}</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="platform.tenant.created" /></label></div>{logs.isError && <div className="alert alert-danger" role="alert">{logs.error.message}<button className="table-action" type="button" onClick={() => void logs.refetch()}>{t('common.retry')}</button></div>}<div className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('platform.actions')}</th><th>{t('platform.actor')}</th><th>{t('platform.tenant')}</th><th>{t('platform.ipAddress')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{logs.isLoading && <tr><td colSpan={5}>{t('platform.loadingAudit')}</td></tr>}{!logs.isLoading && rows.map((log) => <tr key={log.id}><td><strong>{log.action}</strong></td><td>{log.actor?.email ?? 'System'}</td><td>{log.tenant_id ?? 'Platform-wide'}</td><td>{log.ip_address ?? '—'}</td><td>{log.created_at ? new Date(log.created_at).toLocaleString() : '—'}</td></tr>)}{!logs.isLoading && !rows.length && <tr><td colSpan={5} className="muted">{t('platform.noAuditRecords')}</td></tr>}</tbody></table></div></div></section>;
}
