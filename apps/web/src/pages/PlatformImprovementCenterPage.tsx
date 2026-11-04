import { AlertTriangle, Plus, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import {
  addImprovementFeedbackNote, convertImprovementFeedback, createImprovementDefect, createImprovementMaturity,
  createImprovementRequest, createImprovementReview, createImprovementRoadmapItem, createImprovementSlo,
  engageImprovementKillSwitch, getImprovementDefectDetail, getImprovementDefects, getImprovementFeedback,
  getImprovementFeedbackDetail, getImprovementMaturity, getImprovementOverview, getImprovementReliability,
  getImprovementRelease, getImprovementReleases, getImprovementRequestDetail,
  getImprovementRequests, getImprovementReviews, getImprovementRoadmap, getImprovementRollouts,
  getImprovementSatisfaction, updateImprovementDefect, updateImprovementFeedback, updateImprovementRelease,
  updateImprovementRequest, updateImprovementRollout,
  type ImprovementDefect, type ImprovementDefectDetail, type ImprovementFeedback, type ImprovementFeedbackDetail,
  type ImprovementMaturityAssessment, type ImprovementOverview, type ImprovementRelease, type ImprovementRequest,
  type ImprovementRequestDetail, type ImprovementReview, type ImprovementRoadmapItem, type ImprovementRollout,
  type ImprovementSlo,
} from '../lib/api';
import { useI18n } from '../lib/i18n';

export type ImprovementMode = 'overview' | 'feedback' | 'requests' | 'roadmap' | 'quality' | 'rollouts' | 'releases' | 'reliability' | 'satisfaction' | 'maturity' | 'reviews';

type ImprovementListPayload =
  | { kind: 'overview'; data: ImprovementOverview }
  | { kind: 'feedback'; data: { data: ImprovementFeedback[]; meta: { current_page: number; last_page: number; total: number } } }
  | { kind: 'requests'; data: { data: ImprovementRequest[]; meta: { current_page: number; last_page: number; total: number } } }
  | { kind: 'roadmap'; data: { items: ImprovementRoadmapItem[]; by_status: Record<string, number> } }
  | { kind: 'quality'; data: { data: ImprovementDefect[]; meta: { current_page: number; last_page: number; total: number } } }
  | { kind: 'rollouts'; data: { rollouts: ImprovementRollout[]; flag_authority_note: string } }
  | { kind: 'releases'; data: { records: ImprovementRelease[]; status_note: string } }
  | { kind: 'reliability'; data: { slos: ImprovementSlo[]; measurement_model: Record<string, string>; incidents: Array<{ id: string; title: string; severity: string; status: string; started_at: string }> } }
  | { kind: 'satisfaction'; data: Record<string, unknown> }
  | { kind: 'maturity'; data: { assessments: ImprovementMaturityAssessment[]; history: ImprovementMaturityAssessment[]; rubric: string[]; methodology_note: string; missing_dimensions: string[] } }
  | { kind: 'reviews'; data: { data: ImprovementReview[]; meta: { current_page: number; last_page: number; total: number } } };

const PAGE_SIZES = [10, 20, 50];

export function PlatformImprovementCenterPage({ mode }: { mode: ImprovementMode }) {
  const { t } = useI18n();
  const [searchParams, setSearchParams] = useSearchParams();
  const update = (key: string, value: string) => { const next = new URLSearchParams(searchParams); if (value) next.set(key, value); else next.delete(key); next.set('page', '1'); setSearchParams(next); };
  const page = Number(searchParams.get('page') ?? '1');
  const perPage = Number(searchParams.get('per_page') ?? '20');
  const search = searchParams.get('search') ?? undefined;
  const status = searchParams.get('status') ?? undefined;
  const severity = searchParams.get('severity') ?? undefined;
  const category = searchParams.get('category') ?? undefined;
  const moduleFilter = searchParams.get('module') ?? undefined;
  const visibility = searchParams.get('visibility') ?? undefined;
  const stage = searchParams.get('stage') ?? undefined;
  const cadence = searchParams.get('cadence') ?? undefined;
  const [selected, setSelected] = useState<string | null>(null);

  const listQuery = useQuery({
    queryKey: ['improvement', mode, { page, perPage, search, status, severity, category, moduleFilter, visibility, stage, cadence }],
    staleTime: 30_000,
    placeholderData: (previous) => previous,
    queryFn: async () => {
      if (mode === 'overview') return { kind: 'overview', data: (await getImprovementOverview()).data } as const;
      if (mode === 'feedback') return { kind: 'feedback', data: await getImprovementFeedback({ page, per_page: perPage, search, status, category, module: moduleFilter }) } as const;
      if (mode === 'requests') return { kind: 'requests', data: await getImprovementRequests({ page, per_page: perPage, search, status, module: moduleFilter }) } as const;
      if (mode === 'roadmap') return { kind: 'roadmap', data: (await getImprovementRoadmap({ status, module: moduleFilter, visibility })).data } as const;
      if (mode === 'quality') return { kind: 'quality', data: await getImprovementDefects({ page, per_page: perPage, search, status, severity, module: moduleFilter }) } as const;
      if (mode === 'rollouts') return { kind: 'rollouts', data: (await getImprovementRollouts({ stage })).data } as const;
      if (mode === 'releases') return { kind: 'releases', data: (await getImprovementReleases({ status })).data } as const;
      if (mode === 'reliability') return { kind: 'reliability', data: (await getImprovementReliability()).data } as const;
      if (mode === 'satisfaction') return { kind: 'satisfaction', data: (await getImprovementSatisfaction({ from: searchParams.get('from') ?? undefined, to: searchParams.get('to') ?? undefined })).data } as const;
      if (mode === 'maturity') return { kind: 'maturity', data: (await getImprovementMaturity()).data } as const;
      return { kind: 'reviews', data: await getImprovementReviews({ page, per_page: perPage, cadence }) } as const;
    },
  });

  const detailQuery = useQuery({
    queryKey: ['improvement-detail', mode, selected],
    enabled: selected !== null,
    queryFn: async () => {
      if (mode === 'feedback' && selected) return (await getImprovementFeedbackDetail(selected)).data as ImprovementFeedbackDetail;
      if (mode === 'requests' && selected) return (await getImprovementRequestDetail(selected)).data as ImprovementRequestDetail;
      if (mode === 'quality' && selected) return (await getImprovementDefectDetail(selected)).data as ImprovementDefectDetail;
      if (mode === 'releases' && selected) return (await getImprovementRelease(selected)).data as ImprovementRelease;
      return null;
    },
  });

  const [mutationError, setMutationError] = useState<string | null>(null);
  const wrap = async (action: () => Promise<unknown>) => { setMutationError(null); try { await action(); await listQuery.refetch(); if (selected) await detailQuery.refetch(); } catch (error) { setMutationError(error instanceof Error ? error.message : String(error)); } };

  const title = t(`improvement.${mode === 'quality' ? 'qualityTitle' : mode === 'rollouts' ? 'rolloutsTitle' : mode === 'reliability' ? 'reliabilityTitle' : mode === 'reviews' ? 'reviewsTitle' : `${mode}Title`}`);
  const description = t(`improvement.${mode === 'quality' ? 'qualityDescription' : mode === 'rollouts' ? 'rolloutsDescription' : mode === 'reliability' ? 'reliabilityDescription' : mode === 'reviews' ? 'reviewsDescription' : `${mode}Description`}`);
  const data = listQuery.data as ImprovementListPayload | undefined;

  return <section className="platform-page page-section">
    <div className="platform-toolbar"><div><p className="eyebrow">{t('nav.improvement')}</p><h1>{title}</h1><p className="muted">{description}</p></div><button className="button button-secondary" type="button" onClick={() => void listQuery.refetch()} disabled={listQuery.isFetching}><RefreshCw size={15} /> {t('common.refresh')}</button></div>

    {(mode === 'feedback' || mode === 'requests' || mode === 'quality') && <div className="platform-overview-filter-bar">
      <label><span>{t('common.search')}</span><input value={searchParams.get('search') ?? ''} onChange={(event) => update('search', event.target.value)} placeholder={t('common.search')} /></label>
      <label><span>{t('improvement.status')}</span><input value={searchParams.get('status') ?? ''} onChange={(event) => update('status', event.target.value)} /></label>
      {mode === 'quality' && <label><span>{t('improvement.severity')}</span><select value={severity ?? ''} onChange={(event) => update('severity', event.target.value)}><option value="">{t('common.noData')}</option>{['P0', 'P1', 'P2', 'P3'].map((s) => <option key={s} value={s}>{s}</option>)}</select></label>}
      {mode === 'feedback' && <label><span>{t('improvement.category')}</span><select value={category ?? ''} onChange={(event) => update('category', event.target.value)}><option value="">{t('common.noData')}</option>{['bug', 'feature_request', 'usability', 'performance', 'documentation', 'other'].map((c) => <option key={c} value={c}>{t(`improvement.category.${c}`)}</option>)}</select></label>}
      <label><span>{t('improvement.module')}</span><input value={searchParams.get('module') ?? ''} onChange={(event) => update('module', event.target.value)} /></label>
    </div>}
    {mode === 'roadmap' && <div className="platform-overview-filter-bar">
      <label><span>{t('improvement.status')}</span><input value={searchParams.get('status') ?? ''} onChange={(event) => update('status', event.target.value)} /></label>
      <label><span>{t('improvement.visibility')}</span><select value={visibility ?? ''} onChange={(event) => update('visibility', event.target.value)}><option value="">{t('common.noData')}</option><option value="internal">{t('improvement.visibilityInternal')}</option><option value="customer_visible">{t('improvement.visibilityCustomer')}</option></select></label>
      <label><span>{t('improvement.module')}</span><input value={searchParams.get('module') ?? ''} onChange={(event) => update('module', event.target.value)} /></label>
    </div>}
    {mode === 'rollouts' && <div className="platform-overview-filter-bar"><label><span>{t('improvement.stage')}</span><select value={stage ?? ''} onChange={(event) => update('stage', event.target.value)}><option value="">{t('common.noData')}</option>{['development', 'internal_cohort', 'pilot', 'wider_rollout', 'general_availability', 'disabled', 'rolled_back'].map((s) => <option key={s} value={s}>{t(`improvement.stage.${s}`)}</option>)}</select></label></div>}
    {mode === 'releases' && <div className="platform-overview-filter-bar"><label><span>{t('improvement.status')}</span><select value={status ?? ''} onChange={(event) => update('status', event.target.value)}><option value="">{t('common.noData')}</option>{['draft', 'ready_for_qa', 'qa_passed', 'approved', 'deploying', 'deployed', 'failed', 'rolled_back', 'aborted'].map((s) => <option key={s} value={s}>{t(`improvement.status.${s}`)}</option>)}</select></label></div>}
    {mode === 'reviews' && <div className="platform-overview-filter-bar"><label><span>{t('improvement.cadence')}</span><select value={cadence ?? ''} onChange={(event) => update('cadence', event.target.value)}><option value="">{t('common.noData')}</option>{['daily', 'weekly', 'biweekly', 'monthly', 'quarterly'].map((c) => <option key={c} value={c}>{t(`improvement.cadence.${c}`)}</option>)}</select></label></div>}

    {listQuery.isError && <div className="alert alert-danger" role="alert">{t('improvement.loadError')}<button className="table-action" type="button" onClick={() => void listQuery.refetch()}>{t('common.retry')}</button></div>}
    {listQuery.isLoading && <div className="panel" role="status">{t('improvement.loading')}</div>}
    {mutationError && <div className="alert alert-danger" role="alert"><AlertTriangle size={14} /> {mutationError}</div>}

    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'overview' && <OverviewPanel value={data.data as ImprovementOverview} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'feedback' && <FeedbackList data={data.data} onSelect={setSelected} page={page} perPage={perPage} setPage={(value) => update('page', String(value))} setPerPage={(value) => update('per_page', String(value))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'requests' && <RequestsList data={data.data} onSelect={setSelected} page={page} perPage={perPage} setPage={(value) => update('page', String(value))} setPerPage={(value) => update('per_page', String(value))} onCreate={(payload) => wrap(() => createImprovementRequest(payload))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'roadmap' && <RoadmapList items={data.data.items} byStatus={data.data.by_status} onSelect={setSelected} onCreate={(payload) => wrap(() => createImprovementRoadmapItem(payload))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'quality' && <DefectsList data={data.data} onSelect={setSelected} page={page} perPage={perPage} setPage={(value) => update('page', String(value))} setPerPage={(value) => update('per_page', String(value))} onCreate={(payload) => wrap(() => createImprovementDefect(payload))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'rollouts' && <RolloutsList value={data.data} onUpdate={(id, payload) => wrap(() => updateImprovementRollout(id, payload))} onKillSwitch={(id, reason) => wrap(() => engageImprovementKillSwitch(id, reason))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'releases' && <ReleasesList value={data.data} onSelect={setSelected} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'reliability' && <ReliabilityPanel value={data.data} onCreate={(payload) => wrap(() => createImprovementSlo(payload))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'satisfaction' && <SatisfactionPanel value={data.data} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'maturity' && <MaturityPanel value={data.data} onCreate={(payload) => wrap(() => createImprovementMaturity(payload))} />}
    {!listQuery.isLoading && !listQuery.isError && data?.kind === 'reviews' && <ReviewsList data={data.data} onCreate={(payload) => wrap(() => createImprovementReview(payload))} />}

    {selected && detailQuery.data && mode === 'feedback' && <FeedbackDetail value={detailQuery.data as ImprovementFeedbackDetail} onClose={() => setSelected(null)} onAction={(payload) => wrap(() => updateImprovementFeedback(selected, payload))} onNote={(visibility, body) => wrap(() => addImprovementFeedbackNote(selected, visibility, body))} onConvert={(type, titleValue, moduleValue) => wrap(() => convertImprovementFeedback(selected, type, titleValue, moduleValue))} />}
    {selected && detailQuery.data && mode === 'requests' && <RequestDetail value={detailQuery.data as ImprovementRequestDetail} onClose={() => setSelected(null)} onAction={(payload) => wrap(() => updateImprovementRequest(selected, payload))} />}
    {selected && detailQuery.data && mode === 'quality' && <DefectDetail value={detailQuery.data as ImprovementDefectDetail} onClose={() => setSelected(null)} onAction={(payload) => wrap(() => updateImprovementDefect(selected, payload))} />}
    {selected && detailQuery.data && mode === 'releases' && <ReleaseDetail value={detailQuery.data as ImprovementRelease} onClose={() => setSelected(null)} onAction={(payload) => wrap(() => updateImprovementRelease(selected, payload))} />}
  </section>;
}

function StatusPill({ value }: { value: string }) { return <span className="badge">{value.replaceAll('_', ' ')}</span>; }

function OverviewPanel({ value }: { value: ImprovementOverview }) {
  const { t, formatNumber, formatDate } = useI18n();
  return <>
    <div className="platform-kpi-grid">
      <article className="platform-kpi"><span>{t('improvement.openFeedback')}</span><strong>{formatNumber(value.feedback.open)}</strong></article>
      <article className="platform-kpi"><span>{t('improvement.openRequests')}</span><strong>{formatNumber(value.feature_requests.open)}</strong></article>
      <article className="platform-kpi"><span>{t('improvement.openDefects')}</span><strong>{formatNumber(value.defects.open_total)}</strong></article>
      <article className="platform-kpi"><span>{t('improvement.criticalDefects')}</span><strong>{formatNumber(value.defects.critical_open)}</strong></article>
      <article className="platform-kpi"><span>{t('improvement.plannedReleases')}</span><strong>{formatNumber(value.planned_releases)}</strong></article>
      <article className="platform-kpi"><span>{t('improvement.currentRelease')}</span><strong>{value.current_release ? value.current_release.version : t('improvement.noCurrentRelease')}</strong><small className="muted">{value.current_release ? `${value.current_release.environment} · ${formatDate(value.current_release.deployed_at)}` : ''}</small></article>
      <article className="platform-kpi"><span>{t('improvement.satisfaction')}</span><strong>{value.customer_satisfaction.state === 'measured' ? formatNumber(value.customer_satisfaction.avg_score) : t('improvement.satisfactionUnknown')}</strong><small className="muted">{value.customer_satisfaction.state === 'measured' ? `${value.customer_satisfaction.response_count} ${t('improvement.responses')}` : ''}</small></article>
    </div>
    <div className="platform-dashboard-grid">
      <ListRows title={t('improvement.reliability')} rows={value.reliability.services.map((service) => ({ label: service.service, value: service.status }))} />
      <ListRows title={t('improvement.qualityTrend')} rows={value.engineering_quality.weeks.slice(-6).map((week) => ({ label: week.week, value: `${week.opened} / ${week.closed}` }))} />
    </div>
    <p className="muted">{t('improvement.adoptionNote')}</p>
    <p className="muted">{formatDate(value.last_updated_at)} · {t('improvement.statusNote')}</p>
  </>;
}

function ListRows({ title, rows }: { title: string; rows: Array<{ label: string; value: string }> }) {
  const { t } = useI18n();
  return <article className="panel"><div className="table-card-heading"><h2>{title}</h2></div><div className="platform-alert-list">{rows.length ? rows.map((row) => <div key={row.label}><span>{row.label}</span><strong>{row.value}</strong></div>) : <p className="muted">{t('common.noData')}</p>}</div></article>;
}

function Pagination({ page, perPage, total, setPage, setPerPage }: { page: number; perPage: number; total: number; setPage: (value: number) => void; setPerPage: (value: number) => void }) {
  const { t } = useI18n();
  const lastPage = Math.max(1, Math.ceil(total / perPage));
  return <div className="platform-actions">
    <select value={perPage} onChange={(event) => setPerPage(Number(event.target.value))} aria-label={t('common.rows')}>{PAGE_SIZES.map((size) => <option key={size} value={size}>{size}</option>)}</select>
    <button className="table-action" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('common.previous')}</button>
    <span className="muted">{t('common.page')} {page} {t('common.of')} {lastPage}</span>
    <button className="table-action" type="button" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>{t('common.next')}</button>
  </div>;
}

function FeedbackList({ data, onSelect, page, perPage, setPage, setPerPage }: { data: { data: Array<{ id: string; feedback_number: string; tenant?: { name: string } | null; subject: string; category: string; status: string; created_at: string }>; meta: { total: number } }; onSelect: (id: string) => void; page: number; perPage: number; setPage: (value: number) => void; setPerPage: (value: number) => void }) {
  const { t, formatDate } = useI18n();
  return <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.subject')}</th><th>{t('improvement.tenant')}</th><th>{t('improvement.category')}</th><th>{t('improvement.status')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{data.data.length ? data.data.map((row) => <tr key={row.id} onClick={() => onSelect(row.id)} className="cursor-pointer"><td>{row.feedback_number} — {row.subject}</td><td>{row.tenant?.name ?? '—'}</td><td>{t(`improvement.category.${row.category}`)}</td><td><StatusPill value={t(`improvement.status.${row.status}`)} /></td><td>{formatDate(row.created_at)}</td></tr>) : <tr><td colSpan={5} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div><Pagination page={page} perPage={perPage} total={data.meta.total} setPage={setPage} setPerPage={setPerPage} /></article>;
}

function FeedbackDetail({ value, onClose, onAction, onNote, onConvert }: { value: ImprovementFeedbackDetail; onClose: () => void; onAction: (payload: Record<string, unknown>) => void; onNote: (visibility: 'public' | 'internal', body: string) => void; onConvert: (type: 'feature_request' | 'defect', title: string, module?: string) => void }) {
  const { t } = useI18n();
  const [noteVisibility, setNoteVisibility] = useState<'public' | 'internal'>('internal');
  const [noteBody, setNoteBody] = useState('');
  const [transitionTo, setTransitionTo] = useState('');
  const [reason, setReason] = useState('');
  const [convertType, setConvertType] = useState<'feature_request' | 'defect'>('feature_request');
  const [convertTitle, setConvertTitle] = useState('');
  const [duplicateOf, setDuplicateOf] = useState('');
  const [ticketId, setTicketId] = useState('');
  return <article className="panel">
    <div className="table-card-heading"><div><h2>{value.feedback_number}</h2><p className="muted">{value.subject}</p></div><button className="table-action" type="button" onClick={onClose}>{t('common.close')}</button></div>
    <p className="muted">{value.message}</p>
    <p className="muted">{t('improvement.submittedBy')}: {value.submitted_by?.name ?? '—'} · {t('improvement.tenant')}: {value.tenant?.name ?? '—'}</p>
    <div className="platform-actions">
      <select value={transitionTo} onChange={(event) => setTransitionTo(event.target.value)} aria-label={t('improvement.changeStatus')}><option value="">{t('improvement.changeStatus')}</option>{value.possible_transitions.map((s) => <option key={s} value={s}>{t(`improvement.status.${s}`)}</option>)}</select>
      <input value={reason} onChange={(event) => setReason(event.target.value)} placeholder={t('improvement.reason')} />
      <button className="button button-primary" type="button" disabled={!transitionTo} onClick={() => { onAction({ status: transitionTo, reason: reason || undefined }); setTransitionTo(''); setReason(''); }}>{t('improvement.changeStatus')}</button>
    </div>
    <div className="platform-actions">
      <input value={duplicateOf} onChange={(event) => setDuplicateOf(event.target.value)} placeholder={`${t('improvement.duplicateOf')} ID`} />
      <button className="button button-secondary" type="button" disabled={!duplicateOf || duplicateOf === value.id} onClick={() => onAction({ duplicate_of_id: duplicateOf })}>{t('improvement.markDuplicate')}</button>
      <input value={ticketId} onChange={(event) => setTicketId(event.target.value)} placeholder={t('improvement.ticketId')} />
      <button className="button button-secondary" type="button" disabled={!ticketId} onClick={() => onAction({ support_ticket_id: ticketId })}>{t('improvement.linkTicket')}</button>
    </div>
    <div className="platform-actions">
      <select value={convertType} onChange={(event) => setConvertType(event.target.value as typeof convertType)}><option value="feature_request">{t('improvement.convertToRequest')}</option><option value="defect">{t('improvement.convertToDefect')}</option></select>
      <input value={convertTitle} onChange={(event) => setConvertTitle(event.target.value)} placeholder={t('improvement.convertTitle')} />
      <button className="button button-secondary" type="button" disabled={!convertTitle} onClick={() => onConvert(convertType, convertTitle)}><Plus size={14} /> {t('improvement.convert')}</button>
    </div>
    <div className="platform-actions">
      <select value={noteVisibility} onChange={(event) => setNoteVisibility(event.target.value as typeof noteVisibility)}><option value="internal">{t('improvement.internalNote')}</option><option value="public">{t('improvement.publicNote')}</option></select>
      <input value={noteBody} onChange={(event) => setNoteBody(event.target.value)} placeholder={t('improvement.notes')} />
      <button className="button button-primary" type="button" disabled={!noteBody} onClick={() => { onNote(noteVisibility, noteBody); setNoteBody(''); }}>{t('improvement.addNote')}</button>
    </div>
    <h3>{t('improvement.notes')}</h3>
    <ul>{value.notes.map((note) => <li key={note.id}><strong>{note.author?.name ?? '—'}</strong> <span className="muted">({note.visibility})</span>: {note.body}</li>)}</ul>
    <h3>{t('improvement.history')}</h3>
    <ul>{value.history.map((entry, index) => <li key={index}>{entry.from_status ?? '—'} → {t(`improvement.status.${entry.to_status}`)} {entry.reason ? `· ${entry.reason}` : ''}</li>)}</ul>
    {value.duplicates.length > 0 && <><h3>{t('improvement.duplicates')}</h3><ul>{value.duplicates.map((duplicate) => <li key={duplicate.id}>{duplicate.feedback_number} — {duplicate.subject} ({t(`improvement.status.${duplicate.status}`)})</li>)}</ul></>}
    {value.ticket_summary && <p className="muted">{t('improvement.linkedTicket')}: {value.ticket_summary.ticket_number} — {value.ticket_summary.subject}</p>}
  </article>;
}

function RequestsList({ data, onSelect, page, perPage, setPage, setPerPage, onCreate }: { data: { data: Array<{ id: string; request_key: string; title: string; status: string; priority: string; effort_estimate: string; owner: { name: string } | null }>; meta: { total: number } }; onSelect: (id: string) => void; page: number; perPage: number; setPage: (value: number) => void; setPerPage: (value: number) => void; onCreate: (payload: Record<string, unknown>) => void }) {
  const { t, formatNumber } = useI18n();
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ title: '', description: '', module: '', priority: 'medium', effort_estimate: '', effort_estimated: false, business_impact: '' });
  return <>
    <div className="platform-actions"><button className="button button-primary" type="button" onClick={() => setCreating((previous) => !previous)}><Plus size={14} /> {t('improvement.newRequest')}</button></div>
    {creating && <article className="panel">
      <div className="platform-actions">
        <input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder={t('improvement.subject')} />
        <select value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })}>{['critical', 'high', 'medium', 'low'].map((p) => <option key={p} value={p}>{p}</option>)}</select>
      </div>
      <textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder={t('improvement.description')} rows={3} />
      <div className="platform-actions">
        <input value={form.module} onChange={(event) => setForm({ ...form, module: event.target.value })} placeholder={t('improvement.module')} />
        <label><input type="checkbox" checked={form.effort_estimated} onChange={(event) => setForm({ ...form, effort_estimated: event.target.checked })} /> {t('improvement.effortEstimated')}</label>
        <input value={form.effort_estimate} onChange={(event) => setForm({ ...form, effort_estimate: event.target.value })} placeholder={t('improvement.effort')} disabled={!form.effort_estimated} />
      </div>
      <button className="button button-primary" type="button" disabled={!form.title || !form.description} onClick={() => { onCreate({ ...form, module: form.module || undefined, effort_estimate: form.effort_estimated ? form.effort_estimate : undefined }); setCreating(false); }}>{t('common.save')}</button>
    </article>}
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.requestKey')}</th><th>{t('improvement.subject')}</th><th>{t('improvement.priority')}</th><th>{t('improvement.effort')}</th><th>{t('improvement.owner')}</th><th>{t('improvement.status')}</th></tr></thead><tbody>{data.data.length ? data.data.map((row) => <tr key={row.id} onClick={() => onSelect(row.id)} className="cursor-pointer"><td>{row.request_key}</td><td>{row.title}</td><td>{row.priority}</td><td>{row.effort_estimate === 'not_estimated' ? t('improvement.notEstimated') : row.effort_estimate}</td><td>{row.owner?.name ?? '—'}</td><td><StatusPill value={t(`improvement.status.${row.status}`)} /></td></tr>) : <tr><td colSpan={6} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div><Pagination page={page} perPage={perPage} total={data.meta.total} setPage={setPage} setPerPage={setPerPage} /></article>
    <p className="muted">{formatNumber(data.meta.total)} · {t('improvement.notEstimated')} = {t('improvement.notEstimated')}</p>
  </>;
}

function RequestDetail({ value, onClose, onAction }: { value: ImprovementRequestDetail; onClose: () => void; onAction: (payload: Record<string, unknown>) => void }) {
  const { t } = useI18n();
  const [transitionTo, setTransitionTo] = useState('');
  const [priorityReason, setPriorityReason] = useState('');
  return <article className="panel">
    <div className="table-card-heading"><div><h2>{value.request_key}</h2><p className="muted">{value.title}</p></div><button className="table-action" type="button" onClick={onClose}>{t('common.close')}</button></div>
    <p className="muted">{value.description}</p>
    <p className="muted">{t('improvement.effort')}: {value.effort_estimate === 'not_estimated' ? t('improvement.notEstimated') : value.effort_estimate}</p>
    <div className="platform-actions">
      <select value={transitionTo} onChange={(event) => setTransitionTo(event.target.value)}><option value="">{t('improvement.changeStatus')}</option>{value.possible_transitions.map((s) => <option key={s} value={s}>{t(`improvement.status.${s}`)}</option>)}</select>
      <input value={priorityReason} onChange={(event) => setPriorityReason(event.target.value)} placeholder={t('improvement.priorityChangeReason')} />
      <button className="button button-primary" type="button" disabled={!transitionTo && !priorityReason} onClick={() => { onAction({ ...(transitionTo ? { status: transitionTo } : {}), ...(priorityReason ? { priority_change_reason: priorityReason } : {}) }); setTransitionTo(''); setPriorityReason(''); }}>{t('common.save')}</button>
    </div>
    <h3>{t('improvement.history')}</h3>
    <ul>{value.events.map((event, index) => <li key={index}>{event.event} — {JSON.stringify(event.payload)}</li>)}</ul>
  </article>;
}

function RoadmapList({ items, byStatus, onSelect, onCreate }: { items: ImprovementRoadmapItem[]; byStatus: Record<string, number>; onSelect: (id: string) => void; onCreate: (payload: Record<string, unknown>) => void }) {
  const { t } = useI18n();
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ title: '', description: '', module: '', priority: 'medium', visibility: 'internal', customer_summary: '', target_quarter: '', completion_criteria: '' });
  return <>
    <div className="platform-actions"><button className="button button-primary" type="button" onClick={() => setCreating((previous) => !previous)}><Plus size={14} /> {t('improvement.newRoadmapItem')}</button></div>
    {creating && <article className="panel">
      <div className="platform-actions">
        <input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder={t('improvement.subject')} />
        <select value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })}>{['critical', 'high', 'medium', 'low'].map((p) => <option key={p} value={p}>{p}</option>)}</select>
        <select value={form.visibility} onChange={(event) => setForm({ ...form, visibility: event.target.value })}><option value="internal">{t('improvement.visibilityInternal')}</option><option value="customer_visible">{t('improvement.visibilityCustomer')}</option></select>
      </div>
      {form.visibility === 'customer_visible' && <input value={form.customer_summary} onChange={(event) => setForm({ ...form, customer_summary: event.target.value })} placeholder={t('improvement.customerSummary')} />}
      <button className="button button-primary" type="button" disabled={!form.title || (form.visibility === 'customer_visible' && !form.customer_summary)} onClick={() => { onCreate({ ...form, module: form.module || undefined, customer_summary: form.customer_summary || undefined, target_quarter: form.target_quarter || undefined, completion_criteria: form.completion_criteria || undefined }); setCreating(false); }}>{t('common.save')}</button>
    </article>}
    <ListRows title={t('improvement.roadmapTitle')} rows={Object.entries(byStatus).map(([key, count]) => ({ label: t(`improvement.status.${key}`), value: String(count) }))} />
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.subject')}</th><th>{t('improvement.status')}</th><th>{t('improvement.priority')}</th><th>{t('improvement.visibility')}</th><th>{t('improvement.targetQuarter')}</th></tr></thead><tbody>{items.length ? items.map((item) => <tr key={item.id} onClick={() => onSelect(item.id)} className="cursor-pointer"><td>{item.title}</td><td><StatusPill value={t(`improvement.status.${item.status}`)} /></td><td>{item.priority}</td><td>{item.visibility === 'customer_visible' ? t('improvement.visibilityCustomer') : t('improvement.visibilityInternal')}</td><td>{item.target_quarter ?? '—'}</td></tr>) : <tr><td colSpan={5} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div></article>
  </>;
}

function DefectsList({ data, onSelect, page, perPage, setPage, setPerPage, onCreate }: { data: { data: ImprovementDefect[]; meta: { total: number } }; onSelect: (id: string) => void; page: number; perPage: number; setPage: (value: number) => void; setPerPage: (value: number) => void; onCreate: (payload: Record<string, unknown>) => void }) {
  const { t, formatDate } = useI18n();
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ title: '', module: '', environment: 'staging', severity: 'P2', reproduction_steps: '', expected_result: '', actual_result: '' });
  return <>
    <div className="platform-actions"><button className="button button-primary" type="button" onClick={() => setCreating((previous) => !previous)}><Plus size={14} /> {t('improvement.newDefect')}</button></div>
    {creating && <article className="panel">
      <div className="platform-actions">
        <input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder={t('improvement.subject')} />
        <select value={form.severity} onChange={(event) => setForm({ ...form, severity: event.target.value })}>{['P0', 'P1', 'P2', 'P3'].map((s) => <option key={s} value={s}>{s}</option>)}</select>
        <select value={form.environment} onChange={(event) => setForm({ ...form, environment: event.target.value })}>{['local', 'staging', 'production'].map((e) => <option key={e} value={e}>{e}</option>)}</select>
      </div>
      <textarea value={form.reproduction_steps} onChange={(event) => setForm({ ...form, reproduction_steps: event.target.value })} placeholder={t('improvement.reproduction')} rows={2} />
      <button className="button button-primary" type="button" disabled={!form.title} onClick={() => { onCreate({ ...form, module: form.module || undefined }); setCreating(false); }}>{t('common.save')}</button>
    </article>}
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.defectNumber')}</th><th>{t('improvement.subject')}</th><th>{t('improvement.severity')}</th><th>{t('improvement.environment')}</th><th>{t('improvement.status')}</th><th>{t('improvement.retestResult')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{data.data.length ? data.data.map((row) => <tr key={row.id} onClick={() => onSelect(row.id)} className="cursor-pointer"><td>{row.defect_number}</td><td>{row.title}</td><td>{row.severity}</td><td>{row.environment}</td><td><StatusPill value={t(`improvement.status.${row.status}`)} /></td><td>{row.retest_result ?? '—'}</td><td>{formatDate(row.created_at)}</td></tr>) : <tr><td colSpan={7} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div><Pagination page={page} perPage={perPage} total={data.meta.total} setPage={setPage} setPerPage={setPerPage} /></article>
  </>;
}

function DefectDetail({ value, onClose, onAction }: { value: ImprovementDefectDetail; onClose: () => void; onAction: (payload: Record<string, unknown>) => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState({ status: '', retest_result: '', root_cause: '', fix_version: '', related_change_ref: '', reopen_reason: '', severity: '', severity_change_reason: '' });
  return <article className="panel">
    <div className="table-card-heading"><div><h2>{value.defect_number}</h2><p className="muted">{value.title}</p></div><button className="table-action" type="button" onClick={onClose}>{t('common.close')}</button></div>
    <p className="muted">{value.reproduction_steps}</p>
    <p className="muted">{t('improvement.rootCause')}: {value.root_cause ?? '—'}</p>
    <p className="muted">{t('improvement.retestResult')}: {value.retest_result ?? '—'} · {t('improvement.status')}: {t(`improvement.status.${value.status}`)}</p>
    <div className="platform-actions">
      <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}><option value="">{t('improvement.changeStatus')}</option>{value.possible_transitions.map((s) => <option key={s} value={s}>{t(`improvement.status.${s}`)}</option>)}</select>
      <select value={form.retest_result} onChange={(event) => setForm({ ...form, retest_result: event.target.value })}><option value="">{t('improvement.retestResult')}</option><option value="pass">pass</option><option value="fail">fail</option><option value="blocked">blocked</option></select>
      <input value={form.reopen_reason} onChange={(event) => setForm({ ...form, reopen_reason: event.target.value })} placeholder={t('improvement.reopenReason')} />
    </div>
    <div className="platform-actions">
      <input value={form.root_cause} onChange={(event) => setForm({ ...form, root_cause: event.target.value })} placeholder={t('improvement.rootCause')} />
      <input value={form.fix_version} onChange={(event) => setForm({ ...form, fix_version: event.target.value })} placeholder={t('improvement.fixVersion')} />
      <input value={form.related_change_ref} onChange={(event) => setForm({ ...form, related_change_ref: event.target.value })} placeholder="PR / commit" />
      <select value={form.severity} onChange={(event) => setForm({ ...form, severity: event.target.value })}><option value="">{t('improvement.severity')}</option>{['P0', 'P1', 'P2', 'P3'].map((s) => <option key={s} value={s}>{s}</option>)}</select>
      <input value={form.severity_change_reason} onChange={(event) => setForm({ ...form, severity_change_reason: event.target.value })} placeholder={t('improvement.severityChangeReason')} />
      <button className="button button-primary" type="button" onClick={() => { onAction({ status: form.status || undefined, retest_result: form.retest_result || undefined, root_cause: form.root_cause || undefined, fix_version: form.fix_version || undefined, related_change_ref: form.related_change_ref || undefined, reopen_reason: form.reopen_reason || undefined, severity: form.severity || undefined, severity_change_reason: form.severity_change_reason || undefined }); setForm({ status: '', retest_result: '', root_cause: '', fix_version: '', related_change_ref: '', reopen_reason: '', severity: '', severity_change_reason: '' }); }}>{t('common.save')}</button>
    </div>
    <h3>{t('improvement.history')}</h3>
    <ul>{value.events.map((event, index) => <li key={index}>{event.event} — {JSON.stringify(event.payload)}</li>)}</ul>
  </article>;
}

function RolloutsList({ value, onUpdate, onKillSwitch }: { value: { rollouts: Array<{ id: string; flag: { key: string; description: string | null; default_enabled: boolean; review_at: string | null; is_sensitive: boolean } | null; stage: string; scope: string; approval_status: string; review_at: string | null; is_active: boolean }>; flag_authority_note: string }; onUpdate: (id: string, payload: Record<string, unknown>) => void; onKillSwitch: (id: string, reason: string) => void }) {
  const { t } = useI18n();
  const [killReason, setKillReason] = useState('');
  return <>
    <p className="muted">{value.flag_authority_note}</p>
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.flag')}</th><th>{t('improvement.stage')}</th><th>{t('improvement.scope')}</th><th>{t('improvement.approvalStatus')}</th><th>{t('improvement.reviewAt')}</th><th>{t('improvement.actions')}</th></tr></thead><tbody>{value.rollouts.length ? value.rollouts.map((rollout) => <tr key={rollout.id}><td>{rollout.flag?.key ?? '—'}</td><td><StatusPill value={t(`improvement.stage.${rollout.stage}`)} /></td><td>{rollout.scope}</td><td>{rollout.approval_status}</td><td>{rollout.review_at ?? '—'}</td><td className="platform-actions">
      <button className="table-action" type="button" onClick={() => onUpdate(rollout.id, { stage: 'internal_cohort' })}>→ {t('improvement.stage.internal_cohort')}</button>
      <button className="table-action" type="button" onClick={() => onUpdate(rollout.id, { stage: 'pilot', approval: true, approval_note: 'approved from rollout console' })}>→ {t('improvement.stage.pilot')}</button>
      <button className="table-action" type="button" onClick={() => onKillSwitch(rollout.id, killReason || 'manual kill switch')}>{t('improvement.killSwitch')}</button>
    </td></tr>) : <tr><td colSpan={6} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div></article>
    <input value={killReason} onChange={(event) => setKillReason(event.target.value)} placeholder={t('improvement.killSwitchReason')} />
    <p className="muted">{t('improvement.killSwitchNote')} · {t('improvement.approvalRequired')}</p>
  </>;
}

function ReleasesList({ value, onSelect }: { value: { records: ImprovementRelease[]; status_note: string }; onSelect: (id: string) => void }) {
  const { t, formatDate } = useI18n();
  return <>
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.releaseId')}</th><th>{t('improvement.version')}</th><th>{t('improvement.environment')}</th><th>{t('improvement.status')}</th><th>{t('improvement.deploymentRef')}</th><th>{t('improvement.deployedAt')}</th></tr></thead><tbody>{value.records.length ? value.records.map((record) => <tr key={record.id} onClick={() => onSelect(record.id)} className="cursor-pointer"><td>{record.release_id ?? '—'}</td><td>{record.version}</td><td>{record.environment}</td><td><StatusPill value={t(`improvement.status.${record.status}`)} /></td><td>{record.deployment_ref ?? '—'}</td><td>{formatDate(record.deployed_at)}</td></tr>) : <tr><td colSpan={6} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div></article>
    <p className="muted">{value.status_note}</p>
  </>;
}

function ReleaseDetail({ value, onClose, onAction }: { value: ImprovementRelease; onClose: () => void; onAction: (payload: Record<string, unknown>) => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState({ status: '', deployment_ref: '', release_notes: '', release_notes_my: '', known_issues: '', audience: value.audience, customer_visible: value.customer_visible });
  return <article className="panel">
    <div className="table-card-heading"><div><h2>{value.release_id ?? value.version}</h2><p className="muted">{value.environment} · {t(`improvement.status.${value.status}`)}</p></div><button className="table-action" type="button" onClick={onClose}>{t('common.close')}</button></div>
    <div className="platform-actions">
      <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}><option value="">{t('improvement.changeStatus')}</option>{['ready_for_qa', 'qa_passed', 'approved', 'deploying', 'deployed', 'failed', 'rolled_back'].map((s) => <option key={s} value={s}>{t(`improvement.status.${s}`)}</option>)}</select>
      <input value={form.deployment_ref} onChange={(event) => setForm({ ...form, deployment_ref: event.target.value })} placeholder={t('improvement.deploymentRef')} />
      <select value={form.audience} onChange={(event) => setForm({ ...form, audience: event.target.value })}>{['platform', 'business', 'both'].map((a) => <option key={a} value={a}>{a}</option>)}</select>
      <label><input type="checkbox" checked={form.customer_visible} onChange={(event) => setForm({ ...form, customer_visible: event.target.checked })} /> {t('improvement.customerVisible')}</label>
    </div>
    <textarea value={form.release_notes} onChange={(event) => setForm({ ...form, release_notes: event.target.value })} placeholder={t('improvement.releaseNotes')} rows={3} />
    <textarea value={form.release_notes_my} onChange={(event) => setForm({ ...form, release_notes_my: event.target.value })} placeholder={t('improvement.releaseNotesMy')} rows={3} />
    <input value={form.known_issues} onChange={(event) => setForm({ ...form, known_issues: event.target.value })} placeholder={t('improvement.knownIssues')} />
    <button className="button button-primary" type="button" onClick={() => onAction({ status: form.status || undefined, deployment_ref: form.deployment_ref || undefined, release_notes: form.release_notes || undefined, release_notes_my: form.release_notes_my || undefined, known_issues: form.known_issues || undefined, audience: form.audience, customer_visible: form.customer_visible })}>{t('common.save')}</button>
    <p className="muted">{t('improvement.deployedRequiresEvidence')} {t('improvement.publishRequiresNotes')}</p>
  </article>;
}

function ReliabilityPanel({ value, onCreate }: { value: { slos: ImprovementSlo[]; measurement_model: Record<string, string>; incidents: Array<{ id: string; title: string; severity: string; status: string; started_at: string }> }; onCreate: (payload: Record<string, unknown>) => void }) {
  const { t, formatNumber, formatDate } = useI18n();
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ name: '', sli_type: 'availability', metric_source: '', target: '99.9', window_days: 30 });
  return <>
    <div className="platform-actions"><button className="button button-primary" type="button" onClick={() => setCreating((previous) => !previous)}><Plus size={14} /> {t('improvement.newSlo')}</button></div>
    {creating && <article className="panel"><div className="platform-actions">
      <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder={t('improvement.sloName')} />
      <select value={form.sli_type} onChange={(event) => setForm({ ...form, sli_type: event.target.value })}>{['availability', 'success_ratio', 'latency', 'queue_completion'].map((s) => <option key={s} value={s}>{s}</option>)}</select>
      <input value={form.metric_source} onChange={(event) => setForm({ ...form, metric_source: event.target.value })} placeholder={t('improvement.metricSource')} />
      <input type="number" step="0.001" value={form.target} onChange={(event) => setForm({ ...form, target: event.target.value })} />
      <input type="number" value={form.window_days} onChange={(event) => setForm({ ...form, window_days: Number(event.target.value) })} />
      <button className="button button-primary" type="button" disabled={!form.name || !form.metric_source} onClick={() => { onCreate(form); setCreating(false); }}>{t('common.save')}</button>
    </div></article>}
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.sloName')}</th><th>{t('improvement.sliType')}</th><th>{t('improvement.target')}</th><th>{t('improvement.observed')}</th><th>{t('improvement.status')}</th><th>{t('improvement.errorBudget')}</th></tr></thead><tbody>{value.slos.length ? value.slos.map((slo) => <tr key={slo.id}><td>{slo.name}</td><td>{slo.sli_type}</td><td>{formatNumber(slo.target)}%</td><td>{slo.observed !== null ? `${formatNumber(slo.observed)}% (${formatNumber(slo.observations_in_window)} ${t('improvement.observations')})` : t('improvement.sloStatus.unknown')}</td><td><StatusPill value={t(`improvement.sloStatus.${slo.status}`)} /></td><td>{slo.error_budget ? `${formatNumber(slo.error_budget.used_seconds ?? 0)}s / ${formatNumber(slo.error_budget.seconds)}s (${slo.error_budget.used_percent !== null ? `${formatNumber(slo.error_budget.used_percent)}% ${t('improvement.budgetUsed')}` : t('improvement.sloStatus.unknown')})` : '—'}</td></tr>) : <tr><td colSpan={6} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div></article>
    <details><summary className="muted">{t('improvement.measurementModel')}</summary><ul>{Object.entries(value.measurement_model).map(([key, text]) => <li key={key}><strong>{key}</strong>: {text}</li>)}</ul></details>
    <ListRows title={t('improvement.incidents')} rows={value.incidents.map((incident) => ({ label: `${incident.severity} · ${incident.status}`, value: `${incident.title} · ${formatDate(incident.started_at)}` }))} />
  </>;
}

function SatisfactionPanel({ value }: { value: Record<string, unknown> }) {
  const { t, formatNumber } = useI18n();
  const summary = value.summary as { response_count: number; avg_score: number | null; state: string } | undefined;
  const byContext = (value.by_context as Array<{ context: string; responses: number; avg_score: number | null }>) ?? [];
  const byScore = (value.by_score as Array<{ score: number; responses: number }>) ?? [];
  return <>
    <div className="platform-kpi-grid">
      <article className="platform-kpi"><span>{t('improvement.responses')}</span><strong>{summary ? formatNumber(summary.response_count) : '—'}</strong></article>
      <article className="platform-kpi"><span>{t('improvement.avgScore')}</span><strong>{summary?.avg_score !== null && summary?.avg_score !== undefined ? formatNumber(summary.avg_score) : t('improvement.satisfactionUnknown')}</strong></article>
    </div>
    <div className="platform-dashboard-grid">
      <ListRows title={t('improvement.byContext')} rows={byContext.map((row) => ({ label: row.context, value: `${row.responses} · ${row.avg_score ?? '—'}` }))} />
      <ListRows title={t('improvement.byScore')} rows={byScore.map((row) => ({ label: `${row.score}★`, value: String(row.responses) }))} />
    </div>
    <p className="muted">{String(value.denominator_policy ?? '')}</p>
  </>;
}

function MaturityPanel({ value, onCreate }: { value: { assessments: Array<{ id: string; dimension: string; period: string; level: string; reviewer: { name: string } | null; assessed_at: string; evidence?: string }>; rubric: string[]; missing_dimensions: string[] }; onCreate: (payload: Record<string, unknown>) => void }) {
  const { t, formatDate } = useI18n();
  const [form, setForm] = useState({ dimension: 'functional_reliability', period: '2026-Q4', level: 'initial', evidence: '', gaps: '', improvement_actions: '' });
  return <>
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.dimension')}</th><th>{t('improvement.period')}</th><th>{t('improvement.level')}</th><th>{t('platform.actor')}</th><th>{t('platform.created')}</th></tr></thead><tbody>{value.assessments.length ? value.assessments.map((assessment) => <tr key={assessment.id}><td>{t(`improvement.dimension.${assessment.dimension}`)}</td><td>{assessment.period}</td><td><StatusPill value={t(`improvement.level.${assessment.level}`)} /></td><td>{assessment.reviewer?.name ?? '—'}</td><td>{formatDate(assessment.assessed_at)}</td></tr>) : <tr><td colSpan={5} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div></article>
    {value.missing_dimensions.length > 0 && <p className="muted">{t('improvement.missingDimensions')}: {value.missing_dimensions.map((dimension) => t(`improvement.dimension.${dimension}`)).join(', ')}</p>}
    <article className="panel">
      <div className="platform-actions">
        <select value={form.dimension} onChange={(event) => setForm({ ...form, dimension: event.target.value })}>{['functional_reliability', 'security', 'data_integrity', 'tenant_isolation', 'test_coverage', 'accessibility', 'localization', 'documentation', 'operational_readiness', 'performance', 'release_quality', 'customer_support'].map((d) => <option key={d} value={d}>{t(`improvement.dimension.${d}`)}</option>)}</select>
        <input value={form.period} onChange={(event) => setForm({ ...form, period: event.target.value })} placeholder={t('improvement.period')} />
        <select value={form.level} onChange={(event) => setForm({ ...form, level: event.target.value })}>{value.rubric.map((level) => <option key={level} value={level}>{t(`improvement.level.${level}`)}</option>)}</select>
      </div>
      <textarea value={form.evidence} onChange={(event) => setForm({ ...form, evidence: event.target.value })} placeholder={t('improvement.evidence')} rows={2} />
      <button className="button button-primary" type="button" disabled={form.evidence.trim().length < 20} onClick={() => onCreate(form)}>{t('improvement.assess')}</button>
    </article>
  </>;
}

function ReviewsList({ data, onCreate }: { data: { data: ImprovementReview[]; meta: { total: number } }; onCreate: (payload: Record<string, unknown>) => void }) {
  const { t, formatDate } = useI18n();
  const [form, setForm] = useState({ cadence: 'weekly', title: '', findings: '', decisions: '', next_action: '', review_date: '' });
  return <>
    <article className="panel"><div className="platform-actions">
      <select value={form.cadence} onChange={(event) => setForm({ ...form, cadence: event.target.value })}>{['daily', 'weekly', 'biweekly', 'monthly', 'quarterly'].map((c) => <option key={c} value={c}>{t(`improvement.cadence.${c}`)}</option>)}</select>
      <input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder={t('improvement.subject')} />
      <input type="date" value={form.review_date} onChange={(event) => setForm({ ...form, review_date: event.target.value })} />
    </div>
    <textarea value={form.findings} onChange={(event) => setForm({ ...form, findings: event.target.value })} placeholder={t('improvement.findings')} rows={2} />
    <textarea value={form.decisions} onChange={(event) => setForm({ ...form, decisions: event.target.value })} placeholder={t('improvement.decisions')} rows={2} />
    <button className="button button-primary" type="button" disabled={!form.title || !form.review_date} onClick={() => { onCreate({ ...form, findings: form.findings || undefined, decisions: form.decisions || undefined, next_action: form.next_action || undefined }); }}>{t('improvement.newReview')}</button>
    </article>
    <article className="platform-table-card"><div className="table-scroll"><table><thead><tr><th>{t('improvement.cadence')}</th><th>{t('improvement.subject')}</th><th>{t('improvement.findings')}</th><th>{t('improvement.reviewDate')}</th></tr></thead><tbody>{data.data.length ? data.data.map((review) => <tr key={review.id}><td>{t(`improvement.cadence.${review.cadence}`)}</td><td>{review.title}</td><td>{review.findings ?? '—'}</td><td>{formatDate(review.review_date)}</td></tr>) : <tr><td colSpan={4} className="muted">{t('common.noData')}</td></tr>}</tbody></table></div></article>
  </>;
}
