import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { closeSupportTicket, createSupportTicket, downloadTicketFile, getSupportTicket, getSupportTickets, reopenSupportTicket, replySupportTicket, type SupportTicketView } from '../lib/api';
import { useI18n } from '../lib/i18n';
type Translate = ReturnType<typeof useI18n>['t'];
type TranslateDate = ReturnType<typeof useI18n>['formatDate'];

const priorities = ['low', 'normal', 'high', 'urgent'] as const;
const categories = ['general', 'billing', 'account_access', 'inventory', 'sales', 'purchasing', 'accounting', 'integration', 'data_import'] as const;

export function BusinessSupportPage() {
  const { t, formatDate } = useI18n();
  const client = useQueryClient();
  const [search, setSearch] = useState('');
  const [openTicket, setOpenTicket] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const invalidate = () => { void client.invalidateQueries({ queryKey: ['business-support'] }); void client.invalidateQueries({ queryKey: ['business-support-detail'] }); };
  const list = useQuery({ queryKey: ['business-support', search], staleTime: 15_000, queryFn: async () => await getSupportTickets({ search: search || undefined }) });
  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('support.workspaceEyebrow')}</p><h1>{t('support.workspaceTitle')}</h1><p className="muted">{t('support.workspaceDescription')}</p></div><div className="platform-actions"><button className="button button-primary" type="button" onClick={() => setShowForm(!showForm)}>{showForm ? t('common.cancel') : t('support.newTicket')}</button></div></div>
    {showForm && <NewTicketForm onDone={() => { setShowForm(false); invalidate(); }} t={t} />}
    {!showForm && <form className="panel" onSubmit={(e) => e.preventDefault()}><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('support.searchPlaceholder')} aria-label={t('common.search')} /></form>}
    {list.isError && <div className="alert alert-danger" role="alert">{t('support.loadError')}<button className="table-action" type="button" onClick={() => void list.refetch()}>{t('common.retry')}</button></div>}
    {list.isLoading && <div className="panel" role="status">{t('ops.loading')}</div>}
    {!list.isLoading && (list.data!.data.length) === 0 && <div className="panel">{t('support.noTickets')} — <button className="table-action" type="button" onClick={() => setShowForm(true)}>{t('support.newTicket')}</button></div>}
    {!list.isLoading && (list.data!.data.length) > 0 && <article className="platform-table-card"><div className="table-scroll">
      <table><thead><tr><th>#</th><th>{t('support.subject')}</th><th>{t('support.category')}</th><th>{t('support.priority')}</th><th>{t('platform.status' as never)}</th><th>{t('support.created')}</th></tr></thead>
      <tbody>{list.data!.data.map((ticket: SupportTicketView) => <tr key={ticket.id} style={{ cursor: 'pointer' }} onClick={() => setOpenTicket(ticket.id === openTicket ? null : ticket.id)}>
        <td><code>{ticket.ticket_number}</code></td><td><strong>{ticket.subject}</strong></td><td>{t(`support.category.${ticket.category}` as never, ticket.category)}</td><td>{t(`support.priority.${ticket.priority}` as never, ticket.priority)}</td>
        <td><span className={`status-badge status-${ticket.status === 'resolved' || ticket.status === 'closed' ? 'healthy' : ticket.status === 'waiting_customer' || ticket.status === 'waiting_support' ? 'degraded' : 'unknown'}`}>{t(`support.status.${ticket.status}` as never, ticket.status)}</span></td>
        <td>{formatDate(ticket.created_at, { dateStyle: 'short', timeStyle: 'short' })}</td>
      </tr>)}</tbody></table>
    </div></article>}
    {openTicket && <TicketDetailSection id={openTicket} refreshAll={invalidate} formatDate={formatDate} t={t} />}
  </section>;
}

function TicketDetailSection({ id, refreshAll, formatDate, t }: { id: string; refreshAll: () => void; formatDate: TranslateDate; t: Translate }) {
  const client = useQueryClient();
  const detail = useQuery({ queryKey: ['business-support-detail', id], queryFn: async () => (await getSupportTicket(id)).data });
  const [body, setBody] = useState('');
  const [files, setFiles] = useState<File[]>([]);
  const [actionError, setActionError] = useState('');
  const reply = useMutation({ mutationFn: () => replySupportTicket(id, body, files), onSuccess: () => { setBody(''); setFiles([]); refreshAll(); void client.invalidateQueries({ queryKey: ['business-support-detail', id] }); } });
  const close = useMutation({ mutationFn: () => closeSupportTicket(id), onSuccess: () => { void client.invalidateQueries({ queryKey: ['business-support-detail', id] }); void client.invalidateQueries({ queryKey: ['business-support'] }); } });
  const reopen = useMutation({ mutationFn: () => reopenSupportTicket(id), onSuccess: () => { void client.invalidateQueries({ queryKey: ['business-support-detail', id] }); void client.invalidateQueries({ queryKey: ['business-support'] }); } });
  if (detail.isError) return <div className="alert alert-danger" role="alert">{t('support.loadError')}</div>;
  if (!detail.data) return <div className="panel" role="status">{t('ops.loading')}</div>;
  const ticket = detail.data;
  return <TicketDetail ticket={ticket} body={body} setBody={setBody} setFiles={setFiles} reply={reply} close={close} reopen={reopen} actionError={actionError} setActionError={setActionError} formatDate={formatDate} t={t} />;
}

function TicketDetail({ ticket, body, setBody, setFiles, reply, close, reopen, actionError, setActionError, formatDate, t }: { ticket: SupportTicketView; body: string; setBody: (v: string) => void; setFiles: (f: File[]) => void; reply: { mutate: () => void; isPending: boolean }; close: { mutate: () => void; isPending: boolean }; reopen: { mutate: () => void; isPending: boolean }; actionError: string; setActionError: (v: string) => void; formatDate: TranslateDate; t: Translate }) {
  const actions: Array<[string, () => void, boolean]> = [
    ...((['open', 'waiting_customer'] as const).includes(ticket.status as 'open' | 'waiting_customer') ? [['closeTicket', close.mutate, close.isPending] as [string, () => void, boolean]] : []),
    ...((['resolved', 'closed'] as const).includes(ticket.status as 'resolved' | 'closed') && ticket.reopenable ? [['reopenTicket', reopen.mutate, reopen.isPending] as [string, () => void, boolean]] : []),
  ];
  return <article className="panel">
    <div className="table-card-heading"><h2>{ticket.ticket_number}</h2><span className="muted">{t('support.firstResponse')}: {ticket.first_response_at ? formatDate(ticket.first_response_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'} · {t('support.resolved')}: {ticket.resolved_at ? formatDate(ticket.resolved_at, { dateStyle: 'short', timeStyle: 'short' }) : '—'}</span></div>
    <p><strong>{ticket.subject}</strong> · {t(`support.category.${ticket.category}` as never, ticket.category)} · {t(`support.sla.${ticket.sla_status}` as never, ticket.sla_status)}</p>
    <div className="support-thread">
      <div className="support-message from-customer"><p className="muted">{ticket.requester?.name} · {formatDate(ticket.created_at, { dateStyle: 'short', timeStyle: 'medium' })}</p><p>{ticket.description}</p></div>
      {(ticket.thread ?? []).filter((message) => message.visibility === 'public' && message.id !== ticket.thread?.[0]?.id).map((message) => (
        <div key={message.id} className={`support-message ${message.author_type === 'customer' ? 'from-customer' : 'from-support'}`}>
          <p className="muted">{message.author?.name ?? t('support.systemAuthor')} · {t(`support.${message.author_type}` as never, message.author_type)} · {formatDate(message.created_at, { dateStyle: 'short', timeStyle: 'medium' })}</p>
          <p>{message.body}</p>
          {message.attachments.map((attachment) => <button key={attachment.id} className="table-action" type="button" onClick={async () => { try { await downloadTicketFile(`/support/tickets/${ticket.id}/attachments/${attachment.id}`, attachment.original_name); } catch { setActionError(t('support.downloadError')); } }}>{attachment.original_name}</button>)}
        </div>))}
    </div>
    {actionError && <div className="alert alert-danger" role="alert">{actionError}</div>}
    <div className="platform-form-grid">
      <label><span>{t('support.replyLabel')}</span><textarea value={body} onChange={(e) => setBody(e.target.value)} rows={3} maxLength={10000} /></label>
      <label><span>{t('support.attachments')}</span><input type="file" multiple onChange={(e) => setFiles(Array.from(e.target.files ?? []))} accept=".pdf,.png,.jpg,.jpeg,.txt,.csv,.zip" /></label>
    </div>
    <div className="platform-actions">
      <button className="button button-primary" type="button" disabled={reply.isPending || !body.trim()} onClick={() => reply.mutate()}>{reply.isPending ? t('ops.loading') : t('support.sendReply')}</button>
      {actions.map(([label, mutate, pending]) => <button key={label} className="button button-secondary" type="button" disabled={pending} onClick={mutate}>{t(label as never)}</button>)}
    </div>
  </article>;
}

function NewTicketForm({ onDone, t }: { onDone: () => void; t: ReturnType<typeof useI18n>['t'] }) {
  const [draft, setDraft] = useState({ subject: '', description: '', priority: 'normal', category: 'general' });
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState('');
  const create = useMutation({ mutationFn: () => createSupportTicket(draft, files), onSuccess: onDone, onError: () => setError(t('support.createError')) });
  return <form className="panel" onSubmit={(e) => { e.preventDefault(); create.mutate(); }}>
    <div className="platform-form-grid">{[
      <label key="subject"><span>{t('support.subject')} *</span><input value={draft.subject} onChange={(e) => setDraft({ ...draft, subject: e.target.value })} required maxLength={200} /></label>,
      <label key="description"><span>{t('support.description')} *</span><textarea value={draft.description} onChange={(e) => setDraft({ ...draft, description: e.target.value })} rows={4} required maxLength={10000} /></label>,
      <label key="priority"><span>{t('support.priority')}</span><select value={draft.priority} onChange={(e) => setDraft({ ...draft, priority: e.target.value })}>{priorities.map((p) => <option key={p} value={p}>{t(`support.priority.${p}` as never, p)}</option>)}</select></label>,
      <label key="category"><span>{t('support.category')}</span><select value={draft.category} onChange={(e) => setDraft({ ...draft, category: e.target.value })}>{categories.map((c) => <option key={c} value={c}>{t(`support.category.${c}` as never, c)}</option>)}</select></label>,
      <label key="files"><span>{t('support.attachments')}</span><input type="file" multiple accept=".pdf,.png,.jpg,.jpeg,.txt,.csv,.zip" onChange={(e) => setFiles(Array.from(e.target.files ?? []))} /></label>,
    ]}</div>
    <p className="muted">{t('support.attachmentPolicy')}</p>
    {error && <div className="alert alert-danger" role="alert">{error}</div>}
    <button className="button button-primary" type="submit" disabled={create.isPending || !draft.subject.trim() || !draft.description.trim()}>{create.isPending ? t('support.creating') : t('support.submitTicket')}</button>
  </form>;
}
