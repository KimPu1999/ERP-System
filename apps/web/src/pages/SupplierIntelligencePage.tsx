import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Bell, ChevronLeft, ChevronRight, Download, RefreshCw, RotateCcw, Search, ShieldAlert } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { createSupplierReportExport, getSupplierAutomations, getSupplierIntelligence, getSupplierNotifications, getSupplierReports, getSuppliers, toggleSupplierAutomation, type SupplierIntelligence, type SupplierReport, type Supplier } from '../lib/api';

function Cards({ data, loading }: { data?: SupplierIntelligence; loading?: boolean }) { const k=data?.kpis; const cards=[['Total suppliers',k?.total_suppliers],['Active suppliers',k?.active_suppliers],['Pending approvals',k?.pending_approvals],['Expiring documents',k?.expiring_documents],['High-risk suppliers',k?.high_risk_suppliers],['Automation failures',k?.automation_failures]]; return <div className="crm-grid">{cards.map(([label,value])=><article className="crm-card" key={label as string}><span className="muted">{label}</span><strong>{loading ? '…' : value ?? '—'}</strong></article>)}</div>; }
function BarList({ title, values, loading }: { title: string; values: Record<string, number>; loading?: boolean }) { const max=Math.max(1,...Object.values(values)); return <article className="card"><h2>{title}</h2>{loading ? <p className="muted supplier-intelligence-state">Loading supplier data…</p> : Object.entries(values).length ? Object.entries(values).map(([label,value])=><div key={label} className="metric-row"><span>{label || 'Uncategorized'}</span><strong>{value}</strong><div className="metric-bar"><i style={{ width: `${Math.round(value/max*100)}%` }} /></div></div>) : <p className="muted supplier-intelligence-state">No eligible data for this scope.</p>}</article>; }

function SupplierReportKpis({ data }: { data?: SupplierIntelligence }) {
  const k = data?.kpis;
  const spend = k?.supplier_spend ?? [];
  return <div className="supplier-report-kpis">
    <article><span>Total suppliers</span><strong>{k?.total_suppliers ?? '—'}</strong></article>
    <article><span>Active suppliers</span><strong>{k?.active_suppliers ?? '—'}</strong></article>
    <article><span>Pending approvals</span><strong>{k?.pending_approvals ?? '—'}</strong></article>
    <article><span>Posted supplier spend</span><strong>{spend.length ? spend.map(item => `${item.amount} ${item.currency ?? ''}`).join(' · ') : '—'}</strong><small>Separated by recorded currency</small></article>
  </div>;
}

function SupplierReportsView({ companyId, reports }: { companyId: string; reports: { data?: { data: SupplierReport[] }; isLoading: boolean; isError: boolean; refetch: () => unknown } }) {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const filters = { company_id: companyId || undefined, search: search || undefined, status: status || undefined, date_from: dateFrom || undefined, date_to: dateTo || undefined };
  const suppliers = useQuery({ queryKey: ['supplier-directory-report', filters, page, perPage], queryFn: () => getSuppliers({ ...filters, page, per_page: perPage, sort: 'name', direction: 'asc' }) });
  const intelligence = useQuery({ queryKey: ['supplier-report-intelligence', companyId, dateFrom, dateTo], queryFn: () => getSupplierIntelligence({ company_id: companyId, date_from: dateFrom || undefined, date_to: dateTo || undefined }), enabled: Boolean(companyId) });
  const exportJob = useMutation({ mutationFn: () => createSupplierReportExport('supplier_directory', filters) });
  const pageData = suppliers.data?.data;
  const report = reports.data?.data.find(item => item.available);
  const clear = () => { setSearch(''); setStatus(''); setDateFrom(''); setDateTo(''); setPage(1); };
  return <div className="supplier-reports-page">
    <h1 className="sr-only">Supplier Reports</h1>
    <div className="supplier-reports-toolbar-head">
      <div className="supplier-report-tabs" role="tablist" aria-label="Available supplier reports">
        <button className="active" type="button" role="tab" aria-selected="true">Supplier Directory</button>
      </div>
      <button className="primary" type="button" disabled={!report || exportJob.isPending} onClick={() => exportJob.mutate()}><Download size={14} />{exportJob.isPending ? 'Preparing…' : 'Export CSV'}</button>
    </div>
    <div className="supplier-report-filter-bar">
      <label className="supplier-report-search"><span>Search supplier</span><div><Search size={15} /><input value={search} onChange={event => { setSearch(event.target.value); setPage(1); }} placeholder="Name, trading name or code" /></div></label>
      <label><span>Status</span><select value={status} onChange={event => { setStatus(event.target.value); setPage(1); }}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label>
      <label><span>From date</span><input type="date" value={dateFrom} onChange={event => { setDateFrom(event.target.value); setPage(1); }} /></label>
      <label><span>To date</span><input type="date" value={dateTo} onChange={event => { setDateTo(event.target.value); setPage(1); }} /></label>
      <div className="supplier-report-filter-actions"><button className="secondary" type="button" onClick={clear} disabled={!search && !status && !dateFrom && !dateTo}><RotateCcw size={14} /> Reset</button><button className="secondary" type="button" onClick={() => { void suppliers.refetch(); void intelligence.refetch(); void reports.refetch(); }}><RefreshCw size={14} /> Refresh</button></div>
    </div>
    <SupplierReportKpis data={intelligence.data?.data} />
    <div className="supplier-report-chart-grid"><BarList title="Supplier status" values={intelligence.data?.data.charts.status_distribution ?? {}} loading={intelligence.isLoading} /><BarList title="Supplier category" values={intelligence.data?.data.charts.category_distribution ?? {}} loading={intelligence.isLoading} /></div>
    <article className="supplier-report-table-card">
      <div className="supplier-report-table-heading"><div><h2>Supplier directory</h2><span className="muted">{pageData?.total ?? 0} matching suppliers</span></div><span className="supplier-report-scope">Company-scoped</span></div>
      {suppliers.isError ? <div className="supplier-report-state error-text">Unable to load supplier report data. <button className="secondary" type="button" onClick={() => void suppliers.refetch()}>Retry</button></div> : <div className="table-scroll"><table><thead><tr><th>Supplier</th><th>Code</th><th>Category</th><th>Status</th><th>Company</th><th>Created</th></tr></thead><tbody>{suppliers.isLoading ? <tr><td colSpan={6}>Loading supplier report…</td></tr> : (pageData?.data ?? []).map((supplier: Supplier) => <tr key={supplier.id}><td><strong>{supplier.trading_name || supplier.name}</strong>{supplier.trading_name && <div className="muted">{supplier.name}</div>}</td><td>{supplier.code}</td><td>{supplier.category || '—'}</td><td><span className="status-badge">{supplier.status}</span></td><td>{supplier.company_id || 'Shared'}</td><td>{supplier.created_at ? supplier.created_at.slice(0, 10) : '—'}</td></tr>)}{!suppliers.isLoading && !pageData?.data.length && <tr><td colSpan={6} className="muted">No suppliers match the selected filters.</td></tr>}</tbody></table></div>}
      <footer className="supplier-report-pagination"><label>Rows<select value={perPage} onChange={event => { setPerPage(Number(event.target.value)); setPage(1); }}><option value={10}>10</option><option value={25}>25</option><option value={50}>50</option><option value={100}>100</option></select></label><span>Page {pageData?.current_page ?? 1} of {pageData?.last_page ?? 1}</span><button className="secondary" type="button" disabled={!pageData || pageData.current_page <= 1} onClick={() => setPage(value => value - 1)}><ChevronLeft size={14} /> Previous</button><button className="secondary" type="button" disabled={!pageData || pageData.current_page >= pageData.last_page} onClick={() => setPage(value => value + 1)}>Next <ChevronRight size={14} /></button></footer>
    </article>
    {exportJob.isSuccess && <p className="success-text">Supplier directory export queued. Check Report Exports for completion.</p>}
    {exportJob.isError && <p className="error-text">The supplier directory export could not be queued.</p>}
    <p className="supplier-report-note">Only the Supplier Directory report is currently enabled by the backend. Purchase summaries, payments, bills, payables, returns and delivery-performance reports require dedicated authoritative report queries before they can be exposed.</p>
  </div>;
}

export function SupplierIntelligencePage({ mode='analytics' }: { mode?: 'analytics'|'reports'|'automations'|'notifications'|'portal'|'reviews' }) {
  const [params]=useSearchParams(); const companyId=params.get('company_id') ?? ''; const intelligence=useQuery({queryKey:['supplier-intelligence',companyId],queryFn:()=>getSupplierIntelligence({company_id:companyId}),enabled:mode==='analytics'&&Boolean(companyId)}); const reports=useQuery({queryKey:['supplier-reports',companyId],queryFn:()=>getSupplierReports(companyId),enabled:mode==='reports'}); const automations=useQuery({queryKey:['supplier-automations',companyId],queryFn:()=>getSupplierAutomations(companyId),enabled:mode==='automations'}); const notifications=useQuery({queryKey:['supplier-notifications'],queryFn:()=>getSupplierNotifications(),enabled:mode==='notifications'}); const toggle=useMutation({mutationFn:(v:{id:string;enabled:boolean})=>toggleSupplierAutomation(v.id,v.enabled),onSuccess:()=>void automations.refetch()}); const title={analytics:'Supplier intelligence',reports:'Supplier reports',automations:'Supplier workflow automation',notifications:'Supplier notification center',portal:'Supplier portal activity',reviews:'Supplier access reviews'}[mode];
  return <section className="page-section purchasing-page"><h1 className="sr-only">{title}</h1>{!companyId && mode !== 'reports'?<div className="crm-empty">Select an authorized company to view supplier intelligence.</div>:mode==='reports'?<SupplierReportsView companyId={companyId} reports={reports}/>:mode==='analytics'?<>{intelligence.isLoading?<div className="supplier-intelligence-state card">Loading supplier intelligence…</div>:intelligence.isError?<div className="supplier-intelligence-state card error-text">Unable to load supplier intelligence. <button className="secondary" type="button" onClick={() => void intelligence.refetch()}>Retry</button></div>:<><Cards data={intelligence.data?.data}/><div className="dashboard-two-col"><BarList title="Supplier status" values={intelligence.data?.data.charts.status_distribution ?? {}}/><BarList title="Supplier category" values={intelligence.data?.data.charts.category_distribution ?? {}}/></div><article className="card"><h2>Posted supplier spend trend</h2><p className="muted">{intelligence.data?.data.definitions.supplier_spend}</p>{intelligence.data?.data.charts.spend_trend.length ? <div className="table-scroll"><table><thead><tr><th>Period</th><th>Currency</th><th>Amount</th></tr></thead><tbody>{intelligence.data.data.charts.spend_trend.map((x)=><tr key={`${x.period}-${x.currency}`}><td>{x.period}</td><td>{x.currency??'—'}</td><td>{x.amount}</td></tr>)}</tbody></table></div> : <p className="muted supplier-intelligence-state">No posted supplier spend is available for this scope and period.</p>}</article></>}</>:mode==='automations'?<article className="card"><h2>Allowlisted supplier automations</h2><p className="muted">Rules start paused and can only execute approved notification, review, renewal, report or monitoring actions.</p><div className="table-scroll"><table><thead><tr><th>Name</th><th>Trigger</th><th>Actions</th><th>Status</th></tr></thead><tbody>{(automations.data?.data.data??[]).map(r=><tr key={r.id}><td>{r.name}</td><td>{r.event_key}</td><td>{(r.actions??[]).map(a=>a.type).join(', ')}</td><td><button className="table-action" onClick={()=>toggle.mutate({id:r.id,enabled:!r.is_active})}>{r.is_active?'Pause':'Enable'}</button></td></tr>)}</tbody></table></div></article>:mode==='notifications'?<article className="card"><h2><Bell size={16}/> Notifications</h2>{(notifications.data?.data.data??[]).map(n=><div className="notification-row" key={n.id}><strong>{n.title}</strong><span className="muted">{n.body}</span></div>)}{!notifications.data?.data.data.length&&<p className="muted">No notifications for this user.</p>}</article>:<article className="card"><h2><ShieldAlert size={16}/> Monitoring view</h2><p className="muted">Portal activity and access-review records are restricted to authorized procurement administrators. Use the API endpoints for paginated review data.</p></article>}</section>;
}
