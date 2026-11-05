import { Plus, RefreshCw, Star } from 'lucide-react';
import { useRef, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import {
  commentBusinessFeedback, getBusinessFeedback, getBusinessFeedbackDetail, getBusinessReleaseNotes,
  getBusinessRoadmap, rateBusinessFeedback, submitBusinessFeedback, type BusinessFeedbackDetail,
} from '../lib/api';
import { useI18n } from '../lib/i18n';

export type BusinessFeedbackMode = 'feedback' | 'releases' | 'roadmap';

export function BusinessFeedbackPage({ mode }: { mode: BusinessFeedbackMode }) {
  const { t } = useI18n();
  const title = mode === 'feedback' ? t('improvement.businessTitle') : mode === 'releases' ? t('improvement.releasesTitleBusiness') : t('improvement.roadmapTitleBusiness');
  const description = mode === 'feedback' ? t('improvement.businessDescription') : mode === 'releases' ? t('improvement.releasesDescriptionBusiness') : t('improvement.roadmapDescriptionBusiness');
  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('app.workspace')}</p><h1>{title}</h1><p className="muted">{description}</p></div></div>
    {mode === 'feedback' && <FeedbackWorkspace />}
    {mode === 'releases' && <ReleaseNotesView />}
    {mode === 'roadmap' && <RoadmapView />}
  </section>;
}

function FeedbackWorkspace() {
  const { t, formatDate } = useI18n();
  const [selected, setSelected] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const listQuery = useQuery({ queryKey: ['business-feedback', page, perPage], queryFn: () => getBusinessFeedback({ page, per_page: perPage }), staleTime: 30_000, placeholderData: (previous) => previous });
  const detailQuery = useQuery({ queryKey: ['business-feedback-detail', selected], enabled: selected !== null, queryFn: () => getBusinessFeedbackDetail(selected as string) });

  const [form, setForm] = useState({ category: 'bug', module: '', subject: '', message: '' });
  const [feedbackError, setFeedbackError] = useState<string | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);
  const submitMutation = useMutation({
    mutationFn: () => submitBusinessFeedback({ category: form.category, module: form.module || undefined, subject: form.subject, message: form.message }, fileInput.current?.files?.[0]),
    onSuccess: async () => { setForm({ category: 'bug', module: '', subject: '', message: '' }); if (fileInput.current) fileInput.current.value = ''; setFeedbackError(null); await listQuery.refetch(); },
    onError: (error) => setFeedbackError(error instanceof Error ? error.message : t('improvement.submitError')),
  });
  const commentMutation = useMutation({ mutationFn: (body: string) => commentBusinessFeedback(selected as string, body), onSuccess: () => void detailQuery.refetch() });
  const rateMutation = useMutation({ mutationFn: (score: number) => rateBusinessFeedback(selected as string, score), onSuccess: () => void detailQuery.refetch() });

  return <>
    <article className="panel business-feedback-submit">
      <div className="table-card-heading"><h2>{t('improvement.submitFeedback')}</h2></div>
      <div className="business-feedback-form-grid">
        <label><span>{t('improvement.category')}</span>
        <select value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })} aria-label={t('improvement.category')}>
          {['bug', 'feature_request', 'usability', 'performance', 'documentation', 'other'].map((category) => <option key={category} value={category}>{t(`improvement.category.${category}`)}</option>)}
        </select></label>
        <label><span>{t('improvement.module')}</span><input value={form.module} onChange={(event) => setForm({ ...form, module: event.target.value })} placeholder={t('improvement.module')} /></label>
        <label><span>{t('improvement.subject')}</span><input value={form.subject} onChange={(event) => setForm({ ...form, subject: event.target.value })} placeholder={t('improvement.subject')} required /></label>
      </div>
      <label className="business-feedback-message"><span>{t('improvement.message')}</span><textarea value={form.message} onChange={(event) => setForm({ ...form, message: event.target.value })} placeholder={t('improvement.message')} rows={4} required /></label>
      <div className="business-feedback-form-footer">
        <label className="business-feedback-file"><span>{t('improvement.attachment')}</span><input ref={fileInput} type="file" accept=".png,.jpg,.jpeg,.pdf,.txt,.csv,.zip,.doc,.docx,.xlsx" /></label>
        <button className="button button-primary" type="button" disabled={!form.subject || !form.message || submitMutation.isPending} onClick={() => submitMutation.mutate()}><Plus size={14} /> {submitMutation.isPending ? t('improvement.submitting') : t('improvement.submitFeedback')}</button>
      </div>
      {submitMutation.isSuccess && <p className="muted" role="status">{t('improvement.submitted')}</p>}
      {feedbackError && <div className="alert alert-danger" role="alert">{feedbackError}</div>}
    </article>

    <article className="platform-table-card business-feedback-list"><div className="table-scroll"><table><thead><tr><th>#</th><th>{t('improvement.subject')}</th><th>{t('improvement.category')}</th><th>{t('improvement.status')}</th><th>{t('platform.created')}</th></tr></thead><tbody>
      {listQuery.isLoading && <tr><td colSpan={5} role="status">{t('common.loading')}</td></tr>}
      {listQuery.isError && <tr><td colSpan={5} className="muted">{t('improvement.loadError')}</td></tr>}
      {!listQuery.isLoading && !listQuery.isError && (listQuery.data?.data ?? []).length === 0 && <tr><td colSpan={5} className="muted">{t('common.noData')}</td></tr>}
      {(listQuery.data?.data ?? []).map((row) => <tr key={row.id} onClick={() => setSelected(row.id)} className="cursor-pointer"><td>{row.feedback_number}</td><td>{row.subject}</td><td>{t(`improvement.category.${row.category}`)}</td><td><span className="badge">{t(`improvement.status.${row.status}`)}</span></td><td>{formatDate(row.created_at)}</td></tr>)}
    </tbody></table></div>
      <div className="business-feedback-pagination">
        <select value={perPage} onChange={(event) => { setPerPage(Number(event.target.value)); setPage(1); }} aria-label={t('common.rows')}>{[10, 20, 50].map((size) => <option key={size} value={size}>{size}</option>)}</select>
        <button className="table-action" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('common.previous')}</button>
        <span className="muted feedback-page-number">{t('common.page')} {page}</span>
        <button className="table-action" type="button" disabled={page >= (listQuery.data?.meta.last_page ?? 1)} onClick={() => setPage(page + 1)}>{t('common.next')}</button>
      </div>
    </article>

    {selected && detailQuery.data && <FeedbackThread value={(detailQuery.data as { data: BusinessFeedbackDetail }).data} onComment={(body) => commentMutation.mutate(body)} onRate={(score) => rateMutation.mutate(score)} onClose={() => setSelected(null)} pending={commentMutation.isPending || rateMutation.isPending} />}
  </>;
}

function FeedbackThread({ value, onComment, onRate, onClose, pending }: { value: BusinessFeedbackDetail; onComment: (body: string) => void; onRate: (score: number) => void; onClose: () => void; pending: boolean }) {
  const { t, formatDate } = useI18n();
  const [body, setBody] = useState('');
  const canRate = ['resolved', 'closed'].includes(value.status) && !value.has_satisfaction_response;
  return <article className="panel">
    <div className="table-card-heading"><div><h2>{value.feedback_number}</h2><p className="muted">{value.subject}</p></div><button className="table-action" type="button" onClick={onClose}>{t('common.close')}</button></div>
    <p className="muted">{value.message}</p>
    <p className="muted">{t('improvement.status')}: {t(`improvement.status.${value.status}`)} · {formatDate(value.created_at)}</p>
    {value.resolution_outcome && <p className="muted">{t('improvement.resolutionOutcome')}: {value.resolution_outcome}</p>}
    <h3>{t('improvement.notes')}</h3>
    <ul>{value.notes.length ? value.notes.map((note) => <li key={note.id}><strong>{note.author?.name ?? '—'}</strong>: {note.body} <span className="muted">({formatDate(note.created_at)})</span></li>) : <li className="muted">{t('common.noData')}</li>}</ul>
    <div className="platform-actions">
      <input value={body} onChange={(event) => setBody(event.target.value)} placeholder={t('improvement.comment')} />
      <button className="button button-secondary" type="button" disabled={!body || pending} onClick={() => { onComment(body); setBody(''); }}>{t('improvement.comment')}</button>
    </div>
    {canRate && <div className="platform-actions">
      <span>{t('improvement.satisfactionPrompt')}</span>
      {[1, 2, 3, 4, 5].map((score) => <button key={score} className="table-action" type="button" disabled={pending} onClick={() => onRate(score)} aria-label={`${score}`}><Star size={14} /> {score}</button>)}
    </div>}
    {value.has_satisfaction_response && <p className="muted">{t('improvement.alreadyRated')}</p>}
  </article>;
}

function ReleaseNotesView() {
  const { t, formatDate } = useI18n();
  const query = useQuery({ queryKey: ['business-release-notes'], queryFn: () => getBusinessReleaseNotes() });
  if (query.isLoading) return <div className="panel" role="status">{t('common.loading')}</div>;
  if (query.isError) return <div className="panel"><div className="alert alert-danger" role="alert">{t('improvement.loadError')}</div><button className="table-action" type="button" onClick={() => void query.refetch()} disabled={query.isFetching}><RefreshCw size={14} /> {query.isFetching ? t('common.loading') : t('common.retry')}</button></div>;
  const notes = query.data?.data ?? [];
  if (!notes.length) return <div className="panel"><p className="muted">{t('improvement.noReleases')}</p></div>;
  const sectionLabels: Record<string, string> = { new_features: t('improvement.newFeatures'), improvements: t('improvement.improvements'), bug_fixes: t('improvement.bugFixes'), security_notices: t('improvement.securityNotices'), required_actions: t('improvement.requiredActions') };
  const sectionItems = (value: unknown): string[] => Array.isArray(value) ? value.map((item) => typeof item === 'string' ? item : typeof item === 'object' && item !== null ? String((item as { title?: unknown; description?: unknown }).title ?? (item as { description?: unknown }).description ?? '') : String(item)).filter(Boolean) : typeof value === 'string' && value.trim() ? [value] : [];
  return <div className="business-release-list">{notes.map((note) => <article className="panel business-release-card" key={note.id}>
    <div className="table-card-heading"><div><p className="eyebrow">{t('improvement.released')}</p><h2>{note.version}</h2></div><div className="muted release-note-dates"><span>{t('improvement.deployedAt')}: {formatDate(note.deployed_at)}</span>{note.published_at && <span>{t('improvement.publishedAt')}: {formatDate(note.published_at)}</span>}</div></div>
    {note.release_notes && <p className="release-note-summary">{note.release_notes}</p>}
    {note.release_notes_my && <p className="muted release-note-burmese">{note.release_notes_my}</p>}
    {Object.entries(note.sections ?? {}).map(([key, value]) => { const items = sectionItems(value); return items.length ? <section className="release-note-section" key={key}><h3>{sectionLabels[key] ?? key.replaceAll('_', ' ')}</h3><ul>{items.map((item, index) => <li key={`${key}-${index}`}>{item}</li>)}</ul></section> : null; })}
    {note.known_limitations && <section className="release-note-section"><h3>{t('improvement.knownLimitations')}</h3><p className="muted">{note.known_limitations}</p></section>}
  </article>)}</div>;
}

function RoadmapView() {
  const { t } = useI18n();
  const query = useQuery({ queryKey: ['business-roadmap'], queryFn: () => getBusinessRoadmap() });
  if (query.isLoading) return <div className="panel" role="status">{t('common.loading')}</div>;
  if (query.isError) return <div className="alert alert-danger" role="alert">{t('improvement.loadError')}</div>;
  const items = query.data?.data ?? [];
  if (!items.length) return <div className="panel"><p className="muted">{t('improvement.noRoadmap')}</p></div>;
  return <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.subject')}</th><th>{t('improvement.description')}</th><th>{t('improvement.status')}</th><th>{t('improvement.targetQuarter')}</th></tr></thead><tbody>{items.map((item) => <tr key={item.id}><td>{item.title}</td><td>{item.summary ?? '—'}</td><td><span className="badge">{t(`improvement.status.${item.status}`)}</span></td><td>{item.target_quarter ?? '—'}</td></tr>)}</tbody></table></div></article>;
}
