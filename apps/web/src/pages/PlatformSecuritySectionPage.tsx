import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight, Eye, Filter, Search } from 'lucide-react';
import { SecurityCenterNavigation } from '../components/SecurityCenterNavigation';
import { SecurityStepUpDialog } from '../components/SecurityStepUpDialog';
import { acknowledgePlatformAlert, getPlatformMfaStatus, getPlatformSecurityAlerts, getPlatformSecurityEvents, getPlatformSecurityPermissions, getPlatformSecuritySessions, getPlatformSecuritySettings, getSecurityTokens, regenerateMfaRecoveryCodes, revokePlatformSession, revokeOtherSessions, revokeSecurityToken, updatePlatformSecuritySettings } from '../lib/api';
import { useI18n } from '../lib/i18n';

type Mode = 'login-activity' | 'sessions' | 'mfa' | 'alerts' | 'permissions' | 'access-history' | 'settings';
type Settings = Record<string, boolean | number>;
type SecurityEventFilters = { search: string; event: string; category: string; result: string; from: string; to: string };
const emptyEventFilters: SecurityEventFilters = { search: '', event: '', category: '', result: '', from: '', to: '' };
const securityEventLabels: Record<string, string> = {
  'auth.login_failed': 'Login failed',
  'auth.login_blocked': 'Login blocked',
  'auth.mfa_enrollment_required': 'MFA enrollment required',
  'auth.mfa_enabled': 'MFA enabled',
  'auth.platform_login_succeeded': 'Platform login succeeded',
  'auth.business_login_succeeded': 'Business login succeeded',
};
function readableSecurityEvent(event: string): string {
  return securityEventLabels[event] ?? event.replaceAll('.', ' · ').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
const modeKeys = { 'login-activity': 'security.mode.loginActivity', sessions: 'security.mode.sessions', mfa: 'security.mode.mfa', alerts: 'security.mode.alerts', permissions: 'security.mode.permissions', 'access-history': 'security.mode.accessHistory', settings: 'security.mode.settings' } as const;

export function PlatformSecuritySectionPage({ mode }: { mode: Mode }) {
  const { t, formatDate } = useI18n();
  const client = useQueryClient();
  const [stepUpAction, setStepUpAction] = useState<(() => Promise<void>) | null>(null);
  const [form, setForm] = useState<Settings>({});
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [eventFilters, setEventFilters] = useState<SecurityEventFilters>(emptyEventFilters);
  const [eventDraft, setEventDraft] = useState<SecurityEventFilters>(emptyEventFilters);
  const [eventPage, setEventPage] = useState(1);
  const [eventPerPage, setEventPerPage] = useState(25);
  const [eventFilterError, setEventFilterError] = useState<string | null>(null);
  const eventMode = mode === 'login-activity' || mode === 'access-history';
  useEffect(() => {
    if (!eventMode) return undefined;
    const timer = window.setTimeout(() => {
      setEventFilters((current) => current.search === eventDraft.search ? current : { ...current, search: eventDraft.search });
      setEventPage(1);
    }, 350);
    return () => window.clearTimeout(timer);
  }, [eventDraft.search, eventMode]);
  const events = useQuery({ queryKey: ['security-events', eventFilters, eventPage, eventPerPage], queryFn: () => getPlatformSecurityEvents({ ...eventFilters, page: eventPage, per_page: eventPerPage }), enabled: eventMode });
  const sessions = useQuery({ queryKey: ['security-sessions'], queryFn: getPlatformSecuritySessions, enabled: mode === 'sessions' });
  const tokens = useQuery({ queryKey: ['security-tokens'], queryFn: getSecurityTokens, enabled: mode === 'sessions' });
  const mfa = useQuery({ queryKey: ['security-mfa'], queryFn: getPlatformMfaStatus, enabled: mode === 'mfa' });
  const alerts = useQuery({ queryKey: ['security-alerts'], queryFn: () => getPlatformSecurityAlerts(), enabled: mode === 'alerts' });
  const permissions = useQuery({ queryKey: ['security-permissions'], queryFn: getPlatformSecurityPermissions, enabled: mode === 'permissions' });
  const settings = useQuery({ queryKey: ['security-settings'], queryFn: getPlatformSecuritySettings, enabled: mode === 'settings' });
  useEffect(() => { if (settings.data?.data) setForm(settings.data.data); }, [settings.data]);
  const revoke = useMutation({ mutationFn: revokePlatformSession, onSuccess: () => void client.invalidateQueries({ queryKey: ['security-sessions'] }) });
  const revokeOthers = useMutation({ mutationFn: revokeOtherSessions, onSuccess: () => void client.invalidateQueries({ queryKey: ['security-sessions'] }) });
  const revokeToken = useMutation({ mutationFn: revokeSecurityToken, onSuccess: () => void client.invalidateQueries({ queryKey: ['security-tokens'] }) });
  const acknowledge = useMutation({ mutationFn: acknowledgePlatformAlert, onSuccess: () => void client.invalidateQueries({ queryKey: ['security-alerts'] }) });
  const saveSettings = useMutation({ mutationFn: updatePlatformSecuritySettings, onSuccess: (result) => { setForm(result.data); void client.invalidateQueries({ queryKey: ['security-settings'] }); } });
  const regenerateCodes = useMutation({ mutationFn: regenerateMfaRecoveryCodes, onSuccess: (result) => setRecoveryCodes(result.recovery_codes) });
  const protect = (action: () => Promise<void>) => setStepUpAction(() => action);
  const queries = [events, sessions, tokens, mfa, alerts, permissions, settings];
  const loading = queries.some((query) => query.isLoading);
  const error = queries.find((query) => query.isError)?.error as Error | undefined;
  const setSetting = (key: string, value: boolean | number) => setForm((current) => ({ ...current, [key]: value }));
  const updateEventDraft = (key: keyof SecurityEventFilters, value: string) => setEventDraft((current) => ({ ...current, [key]: value }));
  const applyEventFilters = () => {
    if (eventDraft.from && eventDraft.to && eventDraft.from > eventDraft.to) {
      setEventFilterError(t('security.invalidDateRange', 'The start date must be before the end date.'));
      return;
    }
    setEventFilterError(null);
    setEventFilters(eventDraft);
    setEventPage(1);
  };
  const resetEventFilters = () => {
    setEventFilterError(null);
    setEventDraft(emptyEventFilters);
    setEventFilters(emptyEventFilters);
    setEventPage(1);
  };
  const eventPageData = events.data?.data;
  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('security.center')}</p><h1>{t(modeKeys[mode])}</h1><p className="muted">{t('security.pageNotice')}</p></div></div>
    <SecurityCenterNavigation />
    {error && <div className="alert alert-danger" role="alert">{error.message}</div>}
    {loading && <div className="panel" role="status">{t('security.loading')}</div>}
    {eventMode && <div className="platform-table-card security-events-card security-event-list-card"><div className="security-event-filter-bar"><label className="security-search-field"><Search size={15} aria-hidden="true" /><span className="sr-only">{t('security.searchEvents', 'Search security events')}</span><input value={eventDraft.search} onChange={(event) => updateEventDraft('search', event.target.value)} placeholder={t('security.searchEvents', 'Search events or accounts')} /></label><label><span>{t('security.eventType', 'Event type')}</span><select value={eventDraft.event} onChange={(event) => updateEventDraft('event', event.target.value)}><option value="">{t('security.allEvents', 'All events')}</option>{Object.entries(securityEventLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label><label><span>{t('security.result', 'Result')}</span><select value={eventDraft.result} onChange={(event) => updateEventDraft('result', event.target.value)}><option value="">{t('security.allResults', 'All results')}</option><option value="success">{t('security.success', 'Success')}</option><option value="failure">{t('security.failure', 'Failure')}</option><option value="blocked">{t('security.blocked', 'Blocked')}</option></select></label><label><span>{t('platform.from', 'From')}</span><input type="date" value={eventDraft.from} onChange={(event) => updateEventDraft('from', event.target.value)} /></label><label><span>{t('platform.to', 'To')}</span><input type="date" value={eventDraft.to} onChange={(event) => updateEventDraft('to', event.target.value)} /></label><div className="security-filter-actions"><button className="button button-primary button-small" type="button" onClick={applyEventFilters}><Filter size={14} />{t('security.applyFilters', 'Apply')}</button><button className="button button-secondary button-small" type="button" onClick={resetEventFilters}>{t('platform.resetFilters', 'Reset')}</button></div></div>{eventFilterError && <div className="field-error security-filter-error" role="alert">{eventFilterError}</div>}<TableEmpty loading={events.isLoading} empty={!eventPageData?.data.length} label={t('security.noAuthEvents')} /><div className="table-scroll"><table><thead><tr><th>{t('security.event')}</th><th>{t('security.account')}</th><th>{t('security.result')}</th><th>{t('security.ip')}</th><th>{t('security.time')}</th><th /></tr></thead><tbody>{(eventPageData?.data ?? []).map((event) => <tr key={event.id}><td><strong title={event.event}>{readableSecurityEvent(event.event)}</strong><small>{event.event}</small></td><td>{event.user?.email ?? t('security.system')}</td><td><span className={`security-result-badge ${event.result ?? 'unknown'}`}>{event.result ?? t('security.unknown')}</span></td><td>{event.ip_address ?? '—'}</td><td>{formatDate(event.created_at)}</td><td><details className="security-event-details"><summary className="table-action"><Eye size={13} />{t('security.view', 'View')}</summary><div><span>{t('security.category', 'Category')}: {event.category ?? '—'}</span><span>{t('security.requestId', 'Request ID')}: {event.request_id ?? '—'}</span><span>{t('security.userAgent', 'User agent')}: {event.user_agent ?? '—'}</span></div></details></td></tr>)}</tbody></table></div>{eventPageData && eventPageData.total > 0 && <div className="security-pagination"><span className="muted">{eventPageData.total} {t('security.events', 'events')}</span><label><span>{t('platform.rows', 'Rows')}</span><select value={eventPerPage} onChange={(event) => { setEventPerPage(Number(event.target.value)); setEventPage(1); }}><option value={10}>10</option><option value={25}>25</option><option value={50}>50</option><option value={100}>100</option></select></label><button className="button button-secondary button-small" type="button" onClick={() => setEventPage((page) => Math.max(1, page - 1))} disabled={eventPage <= 1 || events.isFetching}><ChevronLeft size={14} />{t('common.previous', 'Previous')}</button><span className="security-page-number">{eventPageData.current_page} / {eventPageData.last_page}</span><button className="button button-secondary button-small" type="button" onClick={() => setEventPage((page) => Math.min(eventPageData.last_page, page + 1))} disabled={eventPage >= eventPageData.last_page || events.isFetching}>{t('common.next', 'Next')}<ChevronRight size={14} /></button></div>}</div>}
    {mode === 'sessions' && <><div className="platform-actions"><button className="button button-secondary" type="button" onClick={() => protect(async () => { await revokeOthers.mutateAsync(); })} disabled={revokeOthers.isPending}>{t('security.revokeOtherSessions')}</button></div><div className="platform-table-card"><div className="table-card-heading"><h2>{t('security.browserSessions')}</h2></div><TableEmpty loading={sessions.isLoading} empty={!sessions.data?.data.length} label={t('security.noSessions')} /><div className="table-scroll"><table><thead><tr><th>{t('security.current')}</th><th>{t('security.device')}</th><th>{t('security.ip')}</th><th>{t('security.lastActivity')}</th><th /></tr></thead><tbody>{(sessions.data?.data ?? []).map((session) => <tr key={session.id}><td>{session.current ? t('security.yes') : t('security.no')}</td><td>{session.user_agent || t('security.unknown')}</td><td>{session.ip_address ?? '—'}</td><td>{formatDate(session.last_activity)}</td><td>{!session.current && <button className="table-action" type="button" onClick={() => protect(async () => { await revoke.mutateAsync(session.id); })} disabled={revoke.isPending}>{t('security.revoke')}</button>}</td></tr>)}</tbody></table></div></div><div className="platform-table-card"><div className="table-card-heading"><h2>{t('security.personalTokens')}</h2></div><TableEmpty loading={tokens.isLoading} empty={!tokens.data?.data.length} label={t('security.noTokens')} /><div className="table-scroll"><table><thead><tr><th>{t('security.name')}</th><th>{t('security.abilities')}</th><th>{t('security.expires')}</th><th>{t('security.lastUsed')}</th><th /></tr></thead><tbody>{(tokens.data?.data ?? []).map((token) => <tr key={token.id}><td>{token.name}</td><td>{token.abilities.join(', ')}</td><td>{formatDate(token.expires_at)}</td><td>{formatDate(token.last_used_at)}</td><td><button className="table-action" type="button" onClick={() => protect(async () => { await revokeToken.mutateAsync(String(token.id)); })} disabled={revokeToken.isPending}>{t('security.revoke')}</button></td></tr>)}</tbody></table></div></div></>}
    {mode === 'mfa' && <><div className="platform-table-card"><div className="platform-actions"><button className="button button-secondary" type="button" onClick={() => protect(async () => { await regenerateCodes.mutateAsync(); })} disabled={regenerateCodes.isPending}>{regenerateCodes.isPending ? t('security.generating') : t('security.regenerateRecovery')}</button></div>{recoveryCodes && <div className="alert alert-warning" role="alert">{t('security.saveCodes')}<pre className="recovery-code-list">{recoveryCodes.join('\n')}</pre></div>}</div><div className="platform-table-card"><TableEmpty loading={mfa.isLoading} empty={!mfa.data?.data.admins.length} label={t('security.noAdmins')} /><div className="table-scroll"><table><thead><tr><th>{t('security.administrator')}</th><th>{t('security.email')}</th><th>{t('security.enrollment')}</th><th>{t('security.enabledAt')}</th></tr></thead><tbody>{(mfa.data?.data.admins ?? []).map((admin) => <tr key={admin.id}><td>{admin.name}</td><td>{admin.email}</td><td>{admin.enrolled ? t('security.enrolled') : t('security.notEnrolled')}</td><td>{formatDate(admin.enabled_at)}</td></tr>)}</tbody></table></div></div></>}
    {mode === 'alerts' && <div className="platform-table-card"><TableEmpty loading={alerts.isLoading} empty={!alerts.data?.data.data.length} label={t('security.noAlerts')} /><div className="table-scroll"><table><thead><tr><th>{t('security.severity')}</th><th>{t('security.alert')}</th><th>{t('security.status')}</th><th>{t('security.lastObserved')}</th><th /></tr></thead><tbody>{(alerts.data?.data.data ?? []).map((alert) => <tr key={alert.id}><td>{alert.severity}</td><td><strong>{alert.title}</strong><small className="muted">{alert.description}</small></td><td>{alert.status}</td><td>{formatDate(alert.last_observed_at)}</td><td>{alert.status === 'open' && <button className="table-action" type="button" onClick={() => protect(async () => { await acknowledge.mutateAsync(alert.id); })}>{t('security.acknowledge')}</button>}</td></tr>)}</tbody></table></div></div>}
    {mode === 'permissions' && <div className="platform-table-card"><TableEmpty loading={permissions.isLoading} empty={!permissions.data?.data.roles.length} label={t('security.noRoles')} /><div className="table-scroll"><table><thead><tr><th>{t('security.role')}</th><th>{t('security.slug')}</th><th>{t('security.tenantScope')}</th></tr></thead><tbody>{(permissions.data?.data.roles ?? []).map((role) => <tr key={role.id}><td>{role.name}</td><td>{role.slug}</td><td>{role.tenant_id ?? 'Platform/global'}</td></tr>)}</tbody></table></div></div>}
    {mode === 'settings' && <div className="platform-table-card"><form className="security-settings-form" onSubmit={(event) => { event.preventDefault(); protect(async () => { const payload = { ...form }; delete payload.platform_mfa_required; await saveSettings.mutateAsync(payload); }); }}><div className="security-setting-row"><span className="security-setting-readonly"><span>{t('security.platformMfaRequired')}</span><input type="checkbox" checked={Boolean(form.platform_mfa_required)} disabled aria-label={t('security.platformMfaRequired')} /><small className="muted">{t('security.platformMfaEnvLocked')}</small></span><label>{t('security.businessMfaRequired')}<input type="checkbox" checked={Boolean(form.business_admin_mfa_required)} onChange={(event) => setSetting('business_admin_mfa_required', event.target.checked)} /></label></div><div className="security-setting-row"><label>{t('security.sessionTimeout')}<input type="number" min={15} max={10080} value={Number(form.session_timeout_minutes ?? 120)} onChange={(event) => setSetting('session_timeout_minutes', Number(event.target.value))} /></label><label>{t('security.stepUpValidity')}<input type="number" min={1} max={60} value={Number(form.step_up_validity_minutes ?? 10)} onChange={(event) => setSetting('step_up_validity_minutes', Number(event.target.value))} /></label><label>{t('security.auditRetention')}<input type="number" min={30} max={3650} value={Number(form.audit_retention_days ?? 365)} onChange={(event) => setSetting('audit_retention_days', Number(event.target.value))} /></label></div><button className="primary" type="submit" disabled={saveSettings.isPending}>{saveSettings.isPending ? t('security.saving') : t('security.saveSettings')}</button></form></div>}
    {stepUpAction && <SecurityStepUpDialog onVerified={stepUpAction} onCancel={() => setStepUpAction(null)} />}
  </section>;
}

function TableEmpty({ loading, empty, label }: { loading: boolean; empty: boolean; label: string }) { return !loading && empty ? <div className="security-empty">{label}</div> : null; }
