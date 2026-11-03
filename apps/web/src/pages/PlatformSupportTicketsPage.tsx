import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { getPlatformSupportTicket, getPlatformSupportTickets, getPlatformUsers, replyPlatformSupportTicket, updatePlatformSupportTicket, type SupportTicketView } from '../lib/api';
import { useI18n } from '../lib/i18n';

type Translate = ReturnType<typeof useI18n>['t'];
type TranslateDate = ReturnType<typeof useI18n>['formatDate'];
const priorities = ['low', 'normal', 'high', 'urgent'] as const;
const statuses = ['open', 'in_progress', 'waiting_customer', 'waiting_support', 'resolved', 'closed'] as const;

export function PlatformSupportTicketsPage() {
  const { t, formatDate } = useI18n();
  const client = useQueryClient();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [ticketId, setTicketId] = useState<string | null>(null);
  const list = useQuery({ queryKey: ['platform-support', search, status, priority], staleTime: 10_000, queryFn: async () => await getPlatformSupportTickets({ search: search || undefined, status: status || undefined, priority: priority || undefined }) });
  const detail = useQuery({ enabled: Boolean(ticketId), queryKey: ['platform-support-detail', ticketId], queryFn: async () => (await getPlatformSupportTicket(ticketId!)).data });
  const invalidate = () => { void client.invalidateQueries({ queryKey: ['platform-support'] }); void client.invalidateQueries({ queryKey: ['platform-support-detail'] }); };
  const update = useMutation({ mutationFn: ({ id, data }: { id: string; data: Record<string, unknown> }) => updatePlatformSupportTicket(id, data), onSuccess: invalidate });
  const reply = useMutation({ mutationFn: ({ id, body, visibility }: { id: string; body: string; visibility: 'public' | 'internal' }) => replyPlatformSupportTicket(id, body, visibility), onSuccess: () => { setReplyDraft(''); invalidate(); } });
  const [replyDraft, setReplyDraft] = useState('');
  const [visibility, setVisibility] = useState<'public' | 'internal'>('public');
  const [routeError, setRouteError] = useState('');
  const users = useQuery({ queryKey: ['platform-support-agents'], staleTime: 300_000, queryFn: async () => getPlatformUsers({ status: 'active', per_page: 100 }) });
  const agents: Array<{ id: string; name: string }> = users.data?.data.data.map((u) => ({ id: u.id, name: u.name })) ?? [];

  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('nav.supportCenter')}</p><h1>{t('support.platformTitle')}</h1><p className="muted">{t('support.platformDescription')}</p></div></div>
    <div className="platform-overview-filter-bar">
      <label><span>{t('common.search')}</span><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('support.searchPlaceholder')} /></label>
      <label><span>{t('platform.status' as never)}</span><select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">{t('support.allStatuses')}</option>{statuses.map((s) => <option key={s} value={s}>{t(`support.status.${s}` as never, s)}</option>)}</select></label>
      <label><span>{t('support.priority')}</span><select value={priority} onChange={(e) => setPriority(e.target.value)}><option value="">{t('support.allPriorities')}</option>{priorities.map((p) => <option key={p} value={p}>{t(`support.priority.${p}` as never, p)}</option>)}</select></label>
    </div>
    {list.isError && <div className="alert alert-danger" role="alert">{t('ops.loadError')}<button className="table-action" type="button" onClick={() => void list.refetch()}>{t('common.retry')}</button></div>}
    {list.isLoading && <div className="panel" role="status">{t('ops.loading')}</div>}
    {!list.isLoading && !list.isError && (list.data!.data.length) === 0 && <div className="panel">{t('support.noTickets')}</div>}
    {!list.isLoading && !list.isError && (list.data!.data.length) > 0 && <article className="platform-table-card"><div className="table-scroll">
      <table><thead><tr><th>#</th><th>{t('support.subject')}</th><th>{t('support.workspace')}</th><th>{t('support.priority')}</th><th>{t('platform.status' as never)}</th><th>SLA</th><th>{t('platform.lastObserved' as never)}</th></tr></thead>
        <tbody>{list.data!.data.map((ticket: SupportTicketView) => (
          <tr key={ticket.id} onClick={() => setTicketId(ticket.id)} className={ticketId === ticket.id ? 'active' : ''} style={{ cursor: 'pointer' }}>
            <td><code>{ticket.ticket_number}</code></td>
            <td><strong>{ticket.subject}</strong></td>
            <td>{ticket.tenant?.name ?? '—'}<br /><span className="muted">{ticket.requester?.email}</span></td>
            <td>{t(`support.priority.${ticket.priority}` as never, ticket.priority)}</td>
            <td><span className={`status-badge status-${ticket.status === 'resolved' || ticket.status === 'closed' ? 'healthy' : ticket.status === 'waiting_customer' || ticket.status === 'waiting_support' ? 'degraded' : 'unknown'}`}>{t(`support.status.${ticket.status}` as never, ticket.status)}</span></td>
            <td>{ticket.sla_status === 'not_configured' ? <span className="muted">{t('ops.notConfigured')}</span> : t(`support.sla.${ticket.sla_status}` as never, ticket.sla_status)}</td>
            <td>{formatDate(ticket.created_at, { dateStyle: 'short', timeStyle: 'short' })}</td>
          </tr>
        ))}</tbody>
      </table>
    </div></article>}
    {detail.data && <SupportDetail ticket={detail.data} reply={reply} update={update} replyDraft={replyDraft} setReplyDraft={setReplyDraft} visibility={visibility} setVisibility={setVisibility} routeError={routeError} setRouteError={setRouteError} formatDate={formatDate} t={t} agents={agents} />}
  </section>;
}

type DetailProps = { ticket: SupportTicketView; reply: { mutate: (args: { id: string; body: string; visibility: 'public' | 'internal' }) => void; isPending: boolean }; update: { mutate: (args: { id: string; data: Record<string, unknown> }) => void; isPending: boolean }; replyDraft: string; setReplyDraft: (v: string) => void; visibility: 'public' | 'internal'; setVisibility: (v: 'public' | 'internal') => void; routeError: string; setRouteError: (v: string) => void; formatDate: TranslateDate; t: Translate; agents: Array<{ id: string; name: string }> };

function SupportDetail({ ticket, reply, update, replyDraft, setReplyDraft, visibility, setVisibility, routeError, setRouteError, formatDate, t, agents }: DetailProps) {
  return <article className="panel">
    <div className="table-card-heading"><h2>{ticket.ticket_number}</h2><div className="platform-actions">
      <select value={ticket.priority} onChange={(e) => update.mutate({ id: ticket.id, data: { priority: e.target.value } })} disabled={update.isPending} aria-label={t('support.priority')}>{priorities.map((p) => <option key={p} value={p}>{t(`support.priority.${p}` as never, p)}</option>)}</select>
      <select value={ticket.status} onChange={(e) => update.mutate({ id: ticket.id, data: { status: e.target.value, resolution_notes: e.target.value === 'resolved' ? 'Resolved from platform support console.' : undefined } })} disabled={update.isPending} aria-label={t('platform.status' as never)}>{statuses.map((s) => <option key={s} value={s}>{t(`support.status.${s}` as never, s)}</option>)}</select>
      <select value="" onChange={(e) => { const value = e.target.value; if (value === '__none') return; if (value === '__unassign') { setRouteError(t('support.unassignNote')); update.mutate({ id: ticket.id, data: { assigned_to: null } }); } else { setRouteError(''); update.mutate({ id: ticket.id, data: { assigned_to: value } }); } }} disabled={update.isPending} aria-label={t('support.assignAgent')}>
        <option value="">{ticket.assignee ? `${t('support.assigned')}: ${ticket.assignee.name}` : t('support.unassigned')}</option>{agents.map((agent) => <option key={agent.id} value={agent.id}>{agent.name}</option>)}
      </select>
    </div></div>
    <p><strong>{ticket.subject}</strong> · {ticket.tenant?.name} · {t('support.requester')}: {ticket.requester?.name} ({ticket.requester?.email}) · {t('ops.status')}: {t(`support.sla.${ticket.sla_status}` as never, ticket.sla_status)}</p>
    <p className="muted">{t('support.created')}: {formatDate(ticket.created_at, { dateStyle: 'short', timeStyle: 'short' })} · {t('support.firstResponse')}: {ticket.first_response_at ? formatDate(ticket.first_response_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'} · {t('support.resolved')}: {ticket.resolved_at ? formatDate(ticket.resolved_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'}</p>
    <div className="support-thread">
      {(ticket.thread ?? []).map((message) => <div key={message.id} className={`support-message ${message.author_type === 'customer' ? 'from-customer' : 'from-support'} ${message.visibility === 'internal' ? 'is-internal' : ''}`}>
        <p className="muted">{message.author?.name ?? t('support.systemAuthor')} · {t(`support.${message.author_type}` as never, message.author_type)}{message.visibility === 'internal' ? ` · ${t('support.internalNote')}` : ''} · {formatDate(message.created_at, { dateStyle: 'short', timeStyle: 'medium' })}</p>
        <p>{message.body}</p>
        {message.attachments.length > 0 && <ul>{message.attachments.map((file) => <li key={file.id}>{file.original_name} ({Math.ceil(file.size_bytes / 1024)} KB)</li>)}</ul>}
      </div>)}
    </div>
    {routeError && <div className="alert alert-danger" role="alert">{routeError}</div>}
    <label className="checkbox-row"><input type="checkbox" checked={visibility === 'internal'} onChange={(e) => setVisibility(e.target.checked ? 'internal' : 'public')} /> {t('support.markInternal')}</label>
    <div className="platform-form-grid">
      <label><span>{t('support.replyLabel')}</span><textarea value={replyDraft} onChange={(e) => setReplyDraft(e.target.value)} rows={3} maxLength={10000} /></label>
    </div>
    <button className="button button-primary" type="button" disabled={reply.isPending || !replyDraft.trim()} onClick={() => { reply.mutate({ id: ticket.id, body: replyDraft, visibility }); }}>{reply.isPending ? t('ops.loading') : t('support.sendReply')}</button>
  </article>;
}
