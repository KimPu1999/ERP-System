import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, BarChart3, CheckCircle2, Clock3, Database, HardDriveDownload, RefreshCw, ShieldAlert, Wallet, Wrench, XCircle, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { createMaintenanceWindow, getBackupsStatus, getCostRecords, getMaintenanceWindows, getOperationsDatabase, getOperationsOverview, getOperationsQueues, recordOperationalCost, registerBackup_verification, transitionMaintenanceWindow, type MaintenanceWindow, type OperationalCostRecordType, type OperationsOverview } from '../lib/api';
import { useI18n } from '../lib/i18n';
type Translate = ReturnType<typeof useI18n>['t'];
type TranslateDate = ReturnType<typeof useI18n>['formatDate'];

export type OperationsMode = 'overview' | 'database' | 'queues' | 'maintenance' | 'backups' | 'costs';
const navItems: Array<[OperationsMode, string, LucideIcon]> = [['overview', 'ops.overview', BarChart3], ['database', 'ops.database', Database], ['queues', 'nav.queues', Clock3], ['maintenance', 'ops.maintenance', Wrench], ['backups', 'ops.backups', HardDriveDownload], ['costs', 'ops.costs', Wallet]];

export function PlatformOperationsCenterPage({ mode }: { mode: OperationsMode }) {
  const { t, formatDate, formatNumber } = useI18n();
  const client = useQueryClient();
  const query = useQuery({ queryKey: ['operations', mode], refetchInterval: mode === 'overview' ? 60_000 : false, staleTime: 15_000, queryFn: async () => mode === 'overview' ? (await getOperationsOverview()).data : mode === 'database' ? (await getOperationsDatabase()).data : mode === 'queues' ? (await getOperationsQueues()).data : mode === 'maintenance' ? (await getMaintenanceWindows()).data : mode === 'backups' ? (await getBackupsStatus()).data : (await getCostRecords()).data });
  const invalidate = () => { void client.invalidateQueries({ queryKey: ['operations'] }); };
  const statusBadge = (status: string) => <span className={`ops-status-badge ops-status-${status === 'reported' || status === 'met' || status === 'on_track' || status === 'healthy' ? 'healthy' : status === 'maintenance' ? 'maintenance' : status === 'stale' || status === 'at_risk' || status === 'degraded' ? 'warning' : status === 'down' || status === 'breached' || status === 'failed' || status === 'critical' ? 'critical' : 'unknown'}`} role="status"><span aria-hidden="true" />{t(`ops.state.${status}` as never, status)}</span>;
  return <section className="platform-page page-section">
    <div className="platform-toolbar operations-toolbar"><div><p className="eyebrow">{t('nav.operations')}</p><h1>{t('ops.center')}</h1></div><div className="platform-actions"><span className="operations-updated">{t('platform.lastUpdated', 'Last updated')}: {mode === 'overview' && query.data && (query.data as OperationsOverview).production_health.last_successful_check ? formatDate((query.data as OperationsOverview).production_health.last_successful_check, { dateStyle: 'short', timeStyle: 'short' }) : t('platform.notObserved', 'Not observed')}</span><button className="button button-secondary" type="button" onClick={() => void query.refetch()} disabled={query.isFetching}><RefreshCw size={15} /> {t('common.refresh')}</button></div></div>
    <nav className="operations-tabs" aria-label={t('ops.center')}><div className="tab-scroll">{navItems.map(([key, label, Icon]) => <Link className={mode === key ? 'active' : ''} key={key} to={`/platform/operations${key === 'overview' ? '' : `/${key}`}`} aria-current={mode === key ? 'page' : undefined}><Icon size={16} aria-hidden="true" /><span>{t(label as never)}</span></Link>)}</div></nav>
    {query.isError && <div className="alert alert-danger" role="alert">{t('ops.loadError')}<button className="table-action" type="button" onClick={() => void query.refetch()}>{t('common.retry')}</button></div>}
    {query.isLoading && <div className="panel" role="status">{t('ops.loading')}</div>}
    {!query.isLoading && !query.isError && mode === 'overview' && query.data as OperationsOverview && <OverviewPanel value={query.data as OperationsOverview} formatDate={formatDate} formatNumber={formatNumber} statusBadge={statusBadge} />}
    {!query.isLoading && !query.isError && mode === 'database' && <DatabasePanel value={query.data} t={t} />}
    {!query.isLoading && !query.isError && mode === 'queues' && <QueuesPanel value={query.data as Record<string, unknown>} formatDate={formatDate} t={t} statusBadge={statusBadge} />}
    {!query.isLoading && !query.isError && mode === 'maintenance' && <MaintenancePanel value={(query.data as MaintenanceWindow[] | undefined) ?? []} onDone={invalidate} formatDate={formatDate} t={t} />}
    {!query.isLoading && !query.isError && mode === 'backups' && <BackupsPanel value={query.data} t={t} formatDate={formatDate} onDone={invalidate} statusBadge={statusBadge} />}
    {!query.isLoading && !query.isError && mode === 'costs' && <CostsPanel value={query.data} t={t} onDone={invalidate} />}
  </section>;
}

function OverviewPanel({ value, formatDate, formatNumber, statusBadge }: { value: OperationsOverview; formatDate: TranslateDate; formatNumber: (v: number) => string; statusBadge: (s: string) => React.ReactNode }) {
  const { t } = useI18n();
  const cards = [
    { label: t('ops.productionHealth'), value: statusBadge(value.production_health.status), icon: value.production_health.status === 'healthy' ? CheckCircle2 : value.production_health.status === 'down' ? XCircle : AlertTriangle },
    { label: t('ops.activeIncidents'), value: formatNumber(value.active_incidents.length), icon: ShieldAlert },
    { label: t('ops.openTickets'), value: `${formatNumber(value.support_tickets.open)} / ${formatNumber(value.support_tickets.total)}`, icon: Clock3 },
    { label: t('ops.failedJobs'), value: formatNumber(value.failed_jobs.total), icon: AlertTriangle },
    { label: t('ops.backupStatus'), value: value.backups.last_verification ? statusBadge(value.backups.status) : <span className="ops-neutral-value">{t('ops.notConfigured')}</span>, icon: HardDriveDownload },
    { label: t('ops.currentRelease'), value: value.release.current_version ?? <span className="ops-neutral-value">{t('ops.notConfigured')}</span>, icon: Database },
  ];
  return <>
    <div className="platform-kpi-grid operations-kpi-grid">{cards.map(({ label, value: cardValue, icon: Icon }) => <article className="platform-kpi operations-kpi" key={label}><span className="platform-kpi-icon"><Icon size={16} /></span><span>{label}</span><strong>{cardValue}</strong></article>)}</div>
    <div className="operations-overview-grid">
      <article className="panel operations-service-panel"><div className="table-card-heading"><h2>{t('ops.serviceHealth')}</h2><span className="muted">{value.production_health.last_successful_check ? formatDate(value.production_health.last_successful_check, { dateStyle: 'short', timeStyle: 'short' }) : t('platform.notObserved' as never)}</span></div><div className="table-scroll"><table><thead><tr><th>{t('platform.service' as never)}</th><th>{t('platform.status' as never)}</th><th>{t('platform.lastObserved' as never)}</th><th>{t('platform.detail', 'Details')}</th></tr></thead><tbody>{value.production_health._services.map((service) => <tr key={service.service}><td><strong>{service.service}</strong></td><td>{statusBadge(service.status)}</td><td>{service.last_observed_at ? formatDate(service.last_observed_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td><td className="muted">{value.production_health.limitations?.[service.service] ?? (service.status === 'unknown' ? t('platform.notObserved', 'Not observed') : '—')}</td></tr>)}{!value.production_health._services.length && <tr><td colSpan={4} className="muted">{t('ops.noneObserved')}</td></tr>}</tbody></table></div></article>
      <article className="panel"><div className="table-card-heading"><h2>{t('ops.operationalSignals')}</h2></div><div className="operations-signal-grid"><Signal label={t('ops.performanceAlerts')} value={`${formatNumber(value.performance_alerts.open_total)} · ${formatNumber(value.performance_alerts.open_critical)} ${t('ops.critical')}`} status={value.performance_alerts.open_critical > 0 ? 'critical' : 'healthy'} /> <Signal label={t('nav.queues')} value={value.queue_backlog === null ? t('platform.notObserved', 'Not observed') : formatNumber(value.queue_backlog)} status={value.queue_status} /> <Signal label={t('ops.scheduler')} value={value.scheduler_collector_last_run_at ? formatDate(value.scheduler_collector_last_run_at, { dateStyle: 'short', timeStyle: 'short' }) : t('ops.notConfigured')} status={value.scheduler_status} /> <Signal label={t('ops.capacity')} value={`${value.capacity.database_size} · ${formatNumber(value.capacity.connections.total)}/${formatNumber(value.capacity.connections.max)} ${t('ops.connections')}`} /> <Signal label={t('ops.slaBreached')} value={formatNumber(value.support_tickets.breached)} status={value.support_tickets.breached > 0 ? 'warning' : 'healthy'} /> <Signal label={t('ops.securityMaintenance')} value={<Link to="/platform/security">{t('nav.securityCenter' as never)}</Link>} /></div></article>
      <article className="panel operations-incidents-panel"><div className="table-card-heading"><h2>{t('ops.activeIncidents')}</h2><Link className="text-link" to="/platform/monitoring/incidents">{t('platform.view', 'View')}</Link></div>{value.active_incidents.length === 0 ? <p className="muted">{t('ops.noIncidents')}</p> : <div className="table-scroll"><table><thead><tr><th>{t('platform.title', 'Incident')}</th><th>{t('platform.severity', 'Severity')}</th><th>{t('platform.status', 'Status')}</th><th>{t('platform.lastObserved', 'Detected')}</th></tr></thead><tbody>{value.active_incidents.map((incident) => <tr key={incident.id}><td><strong>{incident.title}</strong></td><td>{statusBadge(incident.severity)}</td><td>{incident.status}</td><td>{incident.started_at ? formatDate(incident.started_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td></tr>)}</tbody></table></div>}</article>
    </div>
  </>;
}

function Signal({ label, value, status }: { label: string; value: React.ReactNode; status?: string }) { return <div className="operations-signal"><span>{label}</span><strong>{status ? <>{value} <span className={`ops-signal-dot ${status}`} aria-label={status} /></> : value}</strong></div>; }

function DatabasePanel({ value, t }: { value: unknown; t: Translate }) {
  const data = value as { connections?: { total?: number; active?: number; lock_waits?: number; max_connections?: number }; largest_tables?: Array<{ table: string; total_size: string; live_rows: number; seq_scans: number; index_scans: number }>; slow_queries?: Array<{ pid: number; started_ago_ms: number; query: string }>; };
  return <div className="platform-dashboard-grid">
    <article className="panel"><h2>{t('ops.connections')}</h2><p>{t('ops.total')}: {data.connections?.total ?? '?'} · {t('ops.active')}: {data.connections?.active ?? '?'} · {t('ops.lockWaits')}: {data.connections?.lock_waits ?? '?'} · {t('ops.max')}: {data.connections?.max_connections ?? '?'}</p></article>
    <article className="panel"><div className="table-card-heading"><h2>{t('ops.largestTables')}</h2><span className="muted">{t('ops.databaseNote')}</span></div>
      <div className="table-scroll"><table><thead><tr><th>{t('ops.table')}</th><th>{t('ops.size')}</th><th>{t('ops.rows')}</th><th>seq</th><th>idx</th></tr></thead><tbody>{(data.largest_tables ?? []).map((row) => <tr key={row.table}><td>{row.table}</td><td>{row.total_size}</td><td>{row.live_rows}</td><td>{row.seq_scans}</td><td>{row.index_scans}</td></tr>)}</tbody></table></div></article>
    <article className="panel"><h2>{t('ops.slowQueries')}</h2>{(data.slow_queries ?? []).length === 0 ? <p className="muted">{t('ops.noneObserved')}</p> : <ul>{(data.slow_queries ?? []).map((q) => <li key={q.pid}><span className="platform-code-block">{q.query}</span></li>)}</ul>}</article>
  </div>;
}

function QueuesPanel({ value, formatDate, t, statusBadge }: { value: Record<string, unknown>; formatDate: TranslateDate; t: Translate; statusBadge: (s: string) => React.ReactNode }) {
  const failed = (value.failed_jobs as Array<{ id?: string; uuid?: string; queue?: string | null; failed_at?: string | null }> | undefined) ?? [];
  return <div className="platform-dashboard-grid">
    <article className="panel"><h2>{t('nav.queues')}</h2><p>{t('ops.backlog')}: {String(value.backlog ?? '?')} · {t('ops.driver')}: {String(value.driver ?? '?')}</p><p>{statusBadge(String(value.status ?? 'unknown'))}</p><p className="muted">{String(value.retry_reason ?? '')}</p></article>
    <article className="panel"><h2>{t('ops.failedJobs')}</h2>{failed.length === 0 ? <p className="muted">{t('ops.noneObserved')}</p> : <div className="table-scroll"><table><thead><tr><th>UUID</th><th>{t('platform.queue' as never)}</th><th>{t('ops.failedAt')}</th></tr></thead><tbody>{failed.map((job) => <tr key={(job.uuid ?? '')+(job.id ?? '')}><td>{job.uuid ?? job.id}</td><td>{job.queue ?? '—'}</td><td>{formatDate(job.failed_at, { dateStyle: 'short', timeStyle: 'short' })}</td></tr>)}</tbody></table></div>}</article>
  </div>;
}

function MaintenancePanel({ value, onDone, formatDate, t }: { value: MaintenanceWindow[]; onDone: () => void; formatDate: TranslateDate; t: Translate }) {
  const client = useQueryClient();
  const [draft, setDraft] = useState<{ title: string; starts_at: string; ends_at: string; rollback_plan: string; impact_description: string }>({ title: '', starts_at: '', ends_at: '', rollback_plan: '', impact_description: '' });
  const create = useMutation({ mutationFn: () => createMaintenanceWindow(draft), onError: undefined, onSuccess: () => { onDone(); void client.invalidateQueries({ queryKey: ['operations'] }); } });
  const transition = useMutation({ mutationFn: ({ id, status }: { id: string; status: string }) => transitionMaintenanceWindow(id, status), onSuccess: onDone });
  const nextFor = (status: string): Array<[string, string]> => status === 'planned' ? [['approved', 'ops.approve']] : status === 'approved' ? [['in_progress', 'ops.start'], ['cancelled', 'ops.cancel']] : status === 'in_progress' ? [['completed', 'ops.complete']] : [];
  return <>
    <form className="panel operations-entry-form operations-backup-form" onSubmit={(event) => { event.preventDefault(); create.mutate(); }}>
      <div className="operations-form-heading"><span className="operations-form-icon"><HardDriveDownload size={17} /></span><div><h2>{t('ops.verificationRecords', 'Backup evidence')}</h2><p className="muted">{t('ops.evidenceNote')}</p></div></div>
      <div className="platform-form-grid">{[
        <label key="title"><span>{t('ops.windowTitle')} *</span><input value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} required maxLength={180} /></label>,
        <label key="starts"><span>{t('ops.startsAt')} *</span><input type="datetime-local" value={draft.starts_at} onChange={(e) => setDraft({ ...draft, starts_at: e.target.value })} required /></label>,
        <label key="ends"><span>{t('ops.endsAt')} *</span><input type="datetime-local" value={draft.ends_at} onChange={(e) => setDraft({ ...draft, ends_at: e.target.value })} required /></label>,
        <label key="rollback"><span>{t('ops.rollbackPlan')} *</span><textarea value={draft.rollback_plan} onChange={(e) => setDraft({ ...draft, rollback_plan: e.target.value })} required maxLength={4000} /></label>,
      ]}</div>
      <button className="button button-primary" type="submit" disabled={create.isPending || !draft.title || !draft.starts_at || !draft.ends_at || !draft.rollback_plan}>{create.isPending ? t('common.saving' as never) : t('ops.scheduleMaintenance')}</button>
      {create.isError && <div className="alert alert-danger" role="alert">{t('ops.maintenanceError')}</div>}
    </form>
    <article className="panel"><div className="table-card-heading"><h2>{t('ops.maintenanceHistory')}</h2><span className="muted">{t('ops.maintenanceNote')}</span></div>
      {value.length === 0 ? <p className="muted">{t('ops.noMaintenance')}</p> : <div className="table-scroll"><table><thead><tr><th>{t('ops.windowTitle')}</th><th>{t('platform.status' as never)}</th><th>{t('ops.startsAt')}</th><th>{t('ops.endsAt')}</th><th>{t('platform.actions' as never)}</th></tr></thead><tbody>{value.map((window) => <tr key={window.id}><td><strong>{window.title}</strong><br /><span className="muted">{window.rollback_plan}</span></td><td>{window.status}</td><td>{formatDate(window.starts_at, { dateStyle: 'short', timeStyle: 'short' })}</td><td>{formatDate(window.ends_at, { dateStyle: 'short', timeStyle: 'short' })}</td><td>{nextFor(window.status).map(([to, label]) => <button key={to} className="table-action" type="button" disabled={transition.isPending} onClick={() => transition.mutate({ id: window.id, status: to })}>{t(label as never)}</button>)}</td></tr>)}</tbody></table></div>}</article>
  </>;
}

function BackupsPanel({ value, t, formatDate, onDone, statusBadge }: { value: unknown; t: Translate; formatDate: TranslateDate; onDone: () => void; statusBadge: (s: string) => React.ReactNode }) {
  const data = value as { status?: string; last_verification?: { backup_taken_at?: string | null; backup_type?: string; verification_status?: string } | null; last_restore_test?: { verified_at?: string | null } | null; records?: Array<{ id: string; backup_taken_at?: string | null; backup_type?: string; verification_status?: string; storage_location?: string | null }>; scheduling?: string; policy?: Record<string, string> };
  const client = useQueryClient();
  const [draft, setDraft] = useState({ backup_taken_at: new Date().toISOString().slice(0, 16), backup_type: 'pg_dump', storage_location: '', verification_status: 'registered' });
  const create = useMutation({ mutationFn: () => registerBackup_verification({ ...draft, backup_taken_at: new Date(draft.backup_taken_at).toISOString() }), onSuccess: () => { onDone(); void client.invalidateQueries({ queryKey: ['operations'] }); } });
  return <>
    <form className="panel" onSubmit={(event) => { event.preventDefault(); create.mutate(); }}>
      <div className="platform-form-grid">{[
        <label key="taken"><span>{t('ops.takenAt')}</span><input type="datetime-local" value={draft.backup_taken_at} onChange={(e) => setDraft({ ...draft, backup_taken_at: e.target.value })} /></label>,
        <label key="type"><span>{t('ops.type')}</span><select value={draft.backup_type} onChange={(e) => setDraft({ ...draft, backup_type: e.target.value })}><option value="pg_dump">pg_dump</option><option value="base_backup">base_backup</option><option value="wal">wal archive</option><option value="files">files</option></select></label>,
        <label key="storage"><span>{t('ops.storage')}</span><input value={draft.storage_location} onChange={(e) => setDraft({ ...draft, storage_location: e.target.value })} placeholder="s3://… / /backups/…" /></label>,
        <label key="status"><span>{t('ops.status')}</span><select value={draft.verification_status} onChange={(e) => setDraft({ ...draft, verification_status: e.target.value })}><option value="registered">{t('ops.registered')}</option><option value="restore_verified">{t('ops.restoreVerified')}</option><option value="failed">{t('ops.failed')}</option></select></label>,
      ]}</div>
      <button className="button button-primary" type="submit" disabled={create.isPending || !draft.backup_taken_at}><HardDriveDownload size={14} /> {t('ops.registerEvidence')}</button>
      {create.isError && <div className="alert alert-danger" role="alert">{t('ops.evidenceError')}</div>}
    </form>
    <div className="platform-dashboard-grid operations-backups-grid">
      <article className="panel operations-backup-summary"><div className="operations-card-heading"><span className="operations-card-icon"><CheckCircle2 size={16} /></span><h2>{t('ops.backupStatus')}</h2></div><p>{statusBadge(data.status ?? 'not_configured')}</p><p className="muted">{String(data.scheduling ?? '')}</p><p className="muted">{t('ops.rpoNote')}</p><p>{t('ops.lastRestoreTest')}: {data.last_restore_test?.verified_at ? formatDate(data.last_restore_test.verified_at, { dateStyle: 'short', timeStyle: 'short' }) : t('ops.noneRecorded')}</p></article>
      <article className="panel operations-backup-policy"><div className="operations-card-heading"><span className="operations-card-icon"><ShieldAlert size={16} /></span><h2>{t('ops.policy')}</h2></div><ul>{Object.entries(data.policy ?? {}).map(([key, text]) => <li key={key}><strong>{key.replaceAll('_', ' ')}</strong><span>{String(text)}</span></li>)}</ul></article>
      <article className="panel operations-backup-records"><div className="operations-card-heading"><span className="operations-card-icon"><Clock3 size={16} /></span><h2>{t('ops.verificationRecords')}</h2></div>{(data.records ?? []).length === 0 ? <p className="muted operations-empty-copy">{t('ops.noBackupRecords')}</p> : <div className="table-scroll"><table><thead><tr><th>{t('ops.takenAt')}</th><th>{t('ops.type')}</th><th>{t('ops.status')}</th><th>{t('ops.storage')}</th></tr></thead><tbody>{(data.records ?? []).map((record) => <tr key={record.id}><td>{formatDate(record.backup_taken_at, { dateStyle: 'short', timeStyle: 'short' })}</td><td>{record.backup_type}</td><td>{record.verification_status}</td><td>{record.storage_location ?? '—'}</td></tr>)}</tbody></table></div>}</article>
    </div>
  </>;
}

function CostsPanel({ value, t, onDone }: { value: unknown; t: Translate; onDone: () => void }) {
  const data = value as { records?: OperationalCostRecordType[]; summaries_by_currency?: Array<{ currency: string; cost_type: string; total: number }>; by_provider?: Array<{ provider: string; currency: string; cost_type: string; total: number }>; provider_integration?: string; currency_policy?: string };
  const client = useQueryClient();
  const [draft, setDraft] = useState({ occurred_on: new Date().toISOString().slice(0, 10), provider: '', category: 'hosting', amount: '', currency: 'USD', cost_type: 'actual' });
  const create = useMutation({ mutationFn: () => recordOperationalCost({ ...draft, amount: Number(draft.amount) }), onSuccess: () => { onDone(); void client.invalidateQueries({ queryKey: ['operations'] }); } });
  return <>
    <form className="panel" onSubmit={(event) => { event.preventDefault(); create.mutate(); }}>
      <div className="platform-form-grid">{[
        <label key="date"><span>{t('ops.date')}</span><input type="date" value={draft.occurred_on} onChange={(e) => setDraft({ ...draft, occurred_on: e.target.value })} /></label>,
        <label key="provider"><span>{t('ops.provider')}</span><input value={draft.provider} onChange={(e) => setDraft({ ...draft, provider: e.target.value })} required maxLength={80} /></label>,
        <label key="amount"><span>{t('ops.amount')}{/* currency label inserted below */}</span><input type="number" step="0.0001" min="0" value={draft.amount} onChange={(e) => setDraft({ ...draft, amount: e.target.value })} required /></label>,
        <label key="currency"><span>{t('ops.currency')}</span><input value={draft.currency} onChange={(e) => setDraft({ ...draft, currency: e.target.value.toUpperCase().slice(0, 3) })} pattern="[A-Z]{3}" required /></label>,
        <label key="type"><span>{t('ops.costType')}</span><select value={draft.cost_type} onChange={(e) => setDraft({ ...draft, cost_type: e.target.value })}><option value="actual">{t('ops.actual')}</option><option value="estimate">{t('ops.estimate')}</option><option value="forecast">{t('ops.forecast')}</option></select></label>,
      ]}</div>
      <button className="button button-primary" type="submit" disabled={create.isPending || !draft.provider || !draft.amount}><Wallet size={14} /> {t('ops.recordCost')}</button>
      {create.isError && <div className="alert alert-danger" role="alert">{t('ops.costError')}</div>}
      <p className="muted">{String(data.provider_integration ?? '')}</p>
    </form>
    <div className="platform-dashboard-grid">
      <article className="panel"><h2>{t('ops.byCurrency')}</h2><div className="table-scroll"><table><thead><tr><th>{t('ops.currency')}</th><th>{t('ops.costType')}</th><th>{t('ops.total')}</th></tr></thead><tbody>{(data.summaries_by_currency ?? []).map((row, index) => <tr key={`${row.currency}-${row.cost_type}-${index}`}><td>{row.currency}</td><td>{t(`ops.${row.cost_type}` as never, row.cost_type)}</td><td>{row.total}</td></tr>)}</tbody></table></div><p className="muted">{String(data.currency_policy ?? '')}</p></article>
      <article className="panel"><h2>{t('ops.byProvider')}</h2><div className="table-scroll"><table><thead><tr><th>{t('ops.provider')}</th><th>{t('ops.currency')}</th><th>{t('ops.costType')}</th><th>{t('ops.total')}</th></tr></thead><tbody>{(data.by_provider ?? []).map((row, index) => <tr key={`${row.provider}-${row.currency}-${row.cost_type}-${index}`}><td>{row.provider}</td><td>{row.currency}</td><td>{t(`ops.${row.cost_type}` as never, row.cost_type)}</td><td>{row.total}</td></tr>)}</tbody></table></div></article>
    </div>
  </>;
}
