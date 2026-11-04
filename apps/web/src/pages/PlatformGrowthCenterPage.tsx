import { type ReactNode } from 'react';
import { RefreshCw } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import {
  getPlatformGrowthAdoption, getPlatformGrowthCustomerSuccess, getPlatformGrowthOnboarding, getPlatformGrowthOverview,
  getPlatformGrowthRetention, type GrowthSignal, type PlatformAnalyticsFilters, type PlatformGrowthAdoption,
  type PlatformGrowthCustomerSuccess, type PlatformGrowthMode, type PlatformGrowthOnboarding, type PlatformGrowthOverview,
  type PlatformGrowthRetention, type PlatformGrowthSuccessRow,
} from '../lib/api';
import { useI18n } from '../lib/i18n';

type GrowthPayload = PlatformGrowthOverview | PlatformGrowthOnboarding | PlatformGrowthAdoption | PlatformGrowthCustomerSuccess | PlatformGrowthRetention;

const PERIODS = ['last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_year'];

export function PlatformGrowthCenterPage({ mode }: { mode: PlatformGrowthMode }) {
  const { t, formatNumber, formatDate } = useI18n();
  const [searchParams, setSearchParams] = useSearchParams();
  const period = (searchParams.get('period') ?? 'last_30_days') as PlatformAnalyticsFilters['period'];
  const filters: PlatformAnalyticsFilters = {
    period, granularity: (searchParams.get('granularity') ?? undefined) as PlatformAnalyticsFilters['granularity'],
    from: searchParams.get('from') ?? undefined, to: searchParams.get('to') ?? undefined,
    status: searchParams.get('status') ?? undefined, plan_id: searchParams.get('plan_id') ?? undefined,
    business_type: searchParams.get('business_type') ?? undefined, search: searchParams.get('search') ?? undefined,
    page: Number(searchParams.get('page') ?? '1'), per_page: 25,
  };
  const queryKey = ['platform-growth-center', mode, filters];
  const query = useQuery({
    queryKey, staleTime: 30_000, placeholderData: (previous) => previous,
    queryFn: async (): Promise<GrowthPayload> => {
      if (mode === 'onboarding') return (await getPlatformGrowthOnboarding(filters)).data;
      if (mode === 'adoption') return (await getPlatformGrowthAdoption(filters)).data;
      if (mode === 'customer-success') return (await getPlatformGrowthCustomerSuccess(filters)).data;
      if (mode === 'retention') return (await getPlatformGrowthRetention(filters)).data;
      return (await getPlatformGrowthOverview(filters)).data;
    },
  });
  const update = (key: string, value: string) => {
    const next = new URLSearchParams(searchParams);
    if (value) next.set(key, value); else next.delete(key);
    next.set('page', '1');
    setSearchParams(next);
  };
  const titleKey = `growth.mode.${mode}`;
  const errorMessage = query.error instanceof Error ? query.error.message : t('growth.unableToLoad');

  return <section className="platform-page page-section">
    <div className="platform-toolbar">
      <div><p className="eyebrow">{t('growth.eyebrow')}</p><h1>{t(titleKey)}</h1><p className="muted">{t('growth.pageDescription')}</p></div>
      <button className="button button-secondary" type="button" onClick={() => void query.refetch()} disabled={query.isFetching}><RefreshCw size={15} /> {t('common.refresh')}</button>
    </div>
    <div className="platform-overview-filter-bar">
      <label><span>{t('growth.reportingPeriod')}</span>
        <select value={period} onChange={(event) => update('period', event.target.value)}>
          {mode === 'overview' ? PERIODS.concat(['daily', 'weekly', 'monthly', 'yearly']).map((value) => <option key={value} value={value}>{t(`growth.period.${value}`)}</option>) : PERIODS.map((value) => <option key={value} value={value}>{t(`growth.period.${value}`)}</option>)}
        </select>
      </label>
      {mode === 'onboarding' && <>
        <label><span>{t('growth.planId')}</span><input value={searchParams.get('plan_id') ?? ''} onChange={(event) => update('plan_id', event.target.value)} placeholder={t('growth.allPlans')} /></label>
        <label><span>{t('growth.businessType')}</span><input value={searchParams.get('business_type') ?? ''} onChange={(event) => update('business_type', event.target.value)} placeholder={t('growth.allTypes')} /></label>
      </>}
      {mode === 'customer-success' && <>
        <label><span>{t('growth.search')}</span><input value={searchParams.get('search') ?? ''} onChange={(event) => update('search', event.target.value)} placeholder={t('growth.search')} /></label>
        <label><span>{t('growth.status')}</span>
          <select value={searchParams.get('status') ?? ''} onChange={(event) => update('status', event.target.value)}>
            <option value="">{t('growth.allStatuses')}</option>
            {['pending', 'active', 'suspended', 'rejected'].map((value) => <option key={value} value={value}>{value}</option>)}
          </select>
        </label>
      </>}
    </div>
    {query.isError && <div className="alert alert-danger" role="alert">{errorMessage}<button className="table-action" type="button" onClick={() => void query.refetch()}>{t('common.retry')}</button></div>}
    {query.isLoading && <div className="panel" role="status">{t('growth.loading')}</div>}
    {!query.isLoading && !query.isError && query.data && <>
      {mode === 'overview' && <OverviewContent data={query.data as PlatformGrowthOverview} formatNumber={formatNumber} formatDate={formatDate} t={t} />}
      {mode === 'onboarding' && <OnboardingContent data={query.data as PlatformGrowthOnboarding} formatNumber={formatNumber} formatDate={formatDate} t={t} />}
      {mode === 'adoption' && <AdoptionContent data={query.data as PlatformGrowthAdoption} formatNumber={formatNumber} formatDate={formatDate} t={t} />}
      {mode === 'customer-success' && <CustomerSuccessContent data={query.data as PlatformGrowthCustomerSuccess} formatNumber={formatNumber} formatDate={formatDate} t={t} searchParams={searchParams} update={update} />}
      {mode === 'retention' && <RetentionContent data={query.data as PlatformGrowthRetention} formatNumber={formatNumber} formatDate={formatDate} t={t} />}
    </>}
  </section>;
}

/* ---- shared display helpers ---- */

type Locale = { t: (key: string, fallback?: string) => string; formatNumber: (value: number | string | null | undefined) => string };
type DateFormatter = (value: string | Date | null | undefined) => string;

function SignalValue({ signal, t, formatNumber }: { signal: GrowthSignal } & Locale) {
  return <>
    <strong>{signal.available && signal.value !== null ? formatNumber(signal.value) : '—'}</strong>
    {!signal.available && signal.reason ? <small className="muted">{t('growth.unavailableValue')}: {signal.reason}</small> : null}
  </>;
}

function MetricTile({ label, value, note }: { label: string; value: string; note?: ReactNode }) {
  return <article className="platform-kpi platform-growth-metric">
    <span>{label}</span>
    <strong>{value}</strong>
    {note ? <small className="muted">{note}</small> : null}
  </article>;
}

function TrendChart({ title, points, formatNumber, t }: { title: string; points: Array<{ bucket: string; registrations: number; activations: number }> } & Locale) {
  return <article className="panel">
    <div className="table-card-heading"><h2>{title}</h2></div>
    <div className="platform-alert-list">
      {points.length ? points.map((point) => <div key={point.bucket}>
        <span>{point.bucket}</span>
        <strong>{formatNumber(point.registrations)} / {formatNumber(point.activations)}</strong>
      </div>) : <p className="muted">{t('growth.noTrendData')}</p>}
    </div>
    <p className="muted">{t('growth.trendLegend')}</p>
  </article>;
}

function DataTable({ headers, rows, empty }: { headers: string[]; rows: Array<Array<ReactNode>>; empty: string }) {
  return <article className="platform-table-card"><div className="table-scroll"><table>
    <thead><tr>{headers.map((header) => <th key={header}>{header}</th>)}</tr></thead>
    <tbody>{rows.length ? rows.map((row, index) => <tr key={index}>{row.map((cell, cellIndex) => <td key={cellIndex}>{cell}</td>)}</tr>) : <tr><td colSpan={headers.length} className="muted">{empty}</td></tr>}</tbody>
  </table></div></article>;
}

function DefinitionList({ title, definitions }: { title: string; definitions: Record<string, string | null | undefined> }) {
  return <article className="panel"><div className="table-card-heading"><h2>{title}</h2></div>
    <div className="platform-alert-list">
      {Object.entries(definitions).map(([key, text]) => text ? <div key={key}><span>{key.replaceAll('_', ' ')}</span><small className="muted">{text}</small></div> : null)}
    </div>
  </article>;
}

/* ---- mode content ---- */

function OverviewContent({ data, formatNumber, t, formatDate }: { data: PlatformGrowthOverview; formatDate: DateFormatter } & Locale) {
  const kpiLabels: Record<string, string> = {
    registered_in_window: t('growth.kpi.registeredInWindow'), total_businesses: t('growth.kpi.totalBusinesses'),
    onboarding_completions_in_window: t('growth.kpi.completionsInWindow'), total_onboarding_completions: t('growth.kpi.totalCompletions'),
    trial_subscriptions: t('growth.kpi.trialSubscriptions'), failed_payment_tenants_in_window: t('growth.kpi.failedPayments'),
    open_support_tickets: t('growth.kpi.openTickets'),
  };
  const statusRows = data.kpis.businesses_by_status as unknown as Array<{ status: string; count: number }>;
  return <>
    <div className="platform-kpi-grid">
      {Object.entries(kpiLabels).map(([key, label]) => <MetricTile key={key} label={label} value={formatNumber((data.kpis as unknown as Record<string, number>)[key])} />)}
      <article className="platform-kpi">
        <span>{t('growth.kpi.businessesByStatus')}</span>
        {statusRows.length ? statusRows.map((row) => <div key={row.status}><span>{row.status}</span><strong>{formatNumber(row.count)}</strong></div>) : <strong>—</strong>}
      </article>
    </div>
    <article className="panel"><div className="table-card-heading"><h2>{t('growth.notConfigured')}</h2></div>
      <div className="platform-alert-list">
        {Object.entries(data.unavailable).map(([key, value]) => <div key={key}><span>{key.replaceAll('_', ' ')}</span><small className="muted">{t('growth.unavailableValue')}: {value.reason}</small></div>)}
      </div>
    </article>
    <DefinitionList title={t('growth.definitions')} definitions={data.definitions} />
    <p className="muted">{t('growth.reportingTimezone')}: {data.timezone} · {t('growth.lastUpdated')}: {formatDate(data.last_updated_at)}</p>
  </>;
}

function OnboardingContent({ data, t, formatNumber, formatDate }: { data: PlatformGrowthOnboarding; formatDate: DateFormatter } & Locale) {
  const metrics = data.metrics;
  const signal = (key: 'verified_registrations' | 'approved_businesses' | 'onboarding_started' | 'onboarding_completed' | 'activation_rate_percent' | 'abandoned_or_stalled') => metrics[key] as { value: number; available?: boolean; reason?: string | null; definition?: string };
  const rate = metrics.required_setup_completion_rate as { value: number | null; available: boolean; coverage: number; reason?: string };
  const time = metrics.time_to_activation as { available: boolean; sample_count: number; average_days: number | null; definition?: string };
  return <>
    <div className="platform-kpi-grid">
      <MetricTile label={t('growth.metric.registrations')} value={String(metrics.registrations)} />
      <MetricTile label={t('growth.metric.verified')} value={signal('verified_registrations').available === false ? '—' : String(signal('verified_registrations').value)} />
      <MetricTile label={t('growth.metric.approved')} value={signal('approved_businesses').available === false ? '—' : String(signal('approved_businesses').value)} note={signal('approved_businesses').reason} />
      <MetricTile label={t('growth.metric.started')} value={String(signal('onboarding_started').value)} />
      <MetricTile label={t('growth.metric.completed')} value={String(signal('onboarding_completed').value)} />
      <MetricTile label={t('growth.metric.activationRate')} value={signal('activation_rate_percent').available === false ? '—' : `${signal('activation_rate_percent').value}%`} note={signal('activation_rate_percent').reason} />
      <MetricTile label={t('growth.metric.setupRate')} value={rate.available ? `${rate.value}% (${t('growth.metric.coverage')} ${rate.coverage}%)` : '—'} note={rate.reason} />
      <MetricTile label={t('growth.metric.stalled')} value={String(signal('abandoned_or_stalled').value)} />
      <MetricTile label={t('growth.metric.timeToActivation')} value={time.available && time.average_days !== null ? `${time.average_days} ${t('growth.days')} (${time.sample_count})` : '—'} note={time.definition} />
    </div>
    <TrendChart title={t('growth.trendTitle')} points={data.trend} t={t} formatNumber={formatNumber} />
    <article className="panel"><div className="table-card-heading"><h2>{t('growth.cohortDefinition')}</h2></div><p className="muted">{data.cohort_definition}</p><p className="muted">{data.privacy}</p></article>
    <p className="muted">{t('growth.lastUpdated')}: {formatDate(data.last_updated_at)}</p>
  </>;
}

function AdoptionContent({ data, formatNumber, t, formatDate }: { data: PlatformGrowthAdoption; formatDate: DateFormatter } & Locale) {
  return <>
    <div className="platform-dashboard-grid">
      <article className="panel"><div className="table-card-heading"><h2>{t('growth.enabledModules')}</h2></div>
        <div className="platform-alert-list">{data.enabled_modules.data.length ? data.enabled_modules.data.map((row) => <div key={row.module_key}><span>{row.module_key}</span><strong>{formatNumber(row.businesses_enabled)}</strong></div>) : <p className="muted">{t('growth.noEnabledModules')}</p>}</div>
        <p className="muted">{data.enabled_modules.definition}</p>
      </article>
      <article className="panel"><div className="table-card-heading"><h2>{t('growth.moduleOpenEvents')}</h2></div>
        <p className="muted">{data.module_opened_events.reason}</p>
      </article>
    </div>
    <DataTable
      headers={[t('growth.moduleKey'), t('growth.businessesWithActions'), t('growth.recordedEvents')]}
      rows={data.recorded_module_actions.data.map((row) => [row.module_key, String(row.businesses_with_actions), String(row.recorded_events)])}
      empty={t('growth.noRecordedActions')}
    />
    <article className="panel"><div className="table-card-heading"><h2>{t('growth.actionDefinition')}</h2></div><p className="muted">{data.recorded_module_actions.definition}</p><p className="muted">{data.retention_note}</p></article>
    <p className="muted">{t('growth.lastUpdated')}: {formatDate(data.last_updated_at)}</p>
  </>;
}

function CustomerSuccessContent({ data, formatNumber, t, formatDate, searchParams, update }: { data: PlatformGrowthCustomerSuccess; searchParams: URLSearchParams; update: (key: string, value: string) => void; formatDate: DateFormatter } & Locale) {
  const rows = data.data.data;
  const page = Number(searchParams.get('page') ?? '1');
  const setPage = (next: number) => update('page', String(next));
  return <>
    <div className="platform-kpi-grid">
      <MetricTile label={t('growth.kpi.pendingApproval')} value={String(data.summary.attention_counts.pending_approval)} />
      <MetricTile label={t('growth.kpi.activeWithoutOnboarding')} value={String(data.summary.attention_counts.active_without_completed_onboarding)} />
      <MetricTile label={t('growth.kpi.openTickets')} value={String(data.summary.attention_counts.open_support_tickets)} />
      <MetricTile label={t('growth.kpi.failedPayments')} value={String(data.summary.attention_counts.failed_payment_tenants_in_window)} />
      <MetricTile label={t('growth.kpi.renewalsSoon')} value={String(data.summary.attention_counts.renewals_within_14_days)} />
    </div>
    <DataTable
      headers={[t('growth.column.business'), t('growth.column.status'), t('growth.column.onboarding'), t('growth.column.openTickets'), t('growth.column.subscription'), t('growth.column.lastActivity'), t('growth.column.followUp')]}
      rows={rows.map((row: PlatformGrowthSuccessRow) => [
        row.name,
        row.status,
        row.onboarding?.completed ? formatDate(String(row.onboarding.completed_at)) : '—',
        formatNumber(row.open_support_tickets),
        row.subscription ? `${row.subscription.state}${row.subscription.plan_name ? ` · ${row.subscription.plan_name}` : ''}` : '—',
        row.last_recorded_activity_at ? formatDate(row.last_recorded_activity_at) : '—',
        row.follow_up?.length ? row.follow_up.join(', ') : '—',
      ])}
      empty={t('growth.noAttentionRows')}
    />
    <div className="platform-actions"><button className="button button-secondary" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('growth.previousPage')}</button><button className="button button-secondary" type="button" disabled={data.data.data.length < 25} onClick={() => setPage(page + 1)}>{t('growth.nextPage')}</button></div>
    <article className="panel"><div className="table-card-heading"><h2>{t('growth.signalsDocumentation')}</h2></div><p className="muted">{data.signals_documentation}</p></article>
    <p className="muted">{t('growth.lastUpdated')}: {formatDate(data.last_updated_at)}</p>
  </>;
}

function RetentionContent({ data, t, formatNumber, formatDate }: { data: PlatformGrowthRetention; formatDate: DateFormatter } & Locale) {
  return <>
    <div className="platform-kpi-grid">
      {Object.entries(data.events).map(([key, signal]) => <article className="platform-kpi" key={key}>
        <span>{key.replaceAll('_', ' ')}</span>
        <SignalValue signal={signal} t={t} formatNumber={formatNumber} />
        <small className="muted">{signal.definition}</small>
      </article>)}
      <article className="platform-kpi">
        <span>{t('growth.metric.churnRate')}</span>
        <SignalValue signal={data.customer_churn_rate_percent} t={t} formatNumber={formatNumber} />
      </article>
    </div>
    <article className="panel"><div className="table-card-heading"><h2>{t('growth.notConfigured')}</h2></div>
      <div className="platform-alert-list">
        {Object.entries({ trial_conversion: data.trial_conversion, revenue_retention: data.revenue_retention }).map(([key, value]) => <div key={key}><span>{key.replaceAll('_', ' ')}</span><small className="muted">{t('growth.unavailableValue')}: {value.reason}</small></div>)}
      </div>
    </article>
    <article className="panel"><div className="table-card-heading"><h2>{t('growth.cohortCurrentState')}</h2></div>
      <DataTable headers={[t('growth.column.cohort'), t('growth.column.registered'), t('growth.column.stillActive'), t('growth.column.retentionPercent')]}
        rows={data.cohorts_current_state.cohorts.map((cohort) => [cohort.cohort, String(cohort.registered), String(cohort.still_active_state), cohort.retention_percent_current_state === null ? '—' : `${cohort.retention_percent_current_state}%`])}
        empty={t('growth.noCohorts')} />
      <p className="muted">{data.cohorts_current_state.definition}</p>
    </article>
    <DefinitionList title={t('growth.definitions')} definitions={data.definitions} />
    <p className="muted">{t('growth.lastUpdated')}: {formatDate(data.last_updated_at)}</p>
  </>;
}

