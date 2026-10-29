import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, ChevronRight, CircleAlert, Plus, RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { BranchFilters } from '../components/BranchFilters';
import { BranchForm } from '../components/BranchForm';
import { BranchSummaryCards } from '../components/BranchSummaryCards';
import { BranchTable } from '../components/BranchTable';
import { ApiError, createBranch, getBranchManagerCandidates, getBranchStaffCandidates, getBranches, getDashboardCompanies, getMe, updateBranch, updateBranchStatus, type Branch, type BranchInput, type BranchListParams } from '../lib/api';

function filtersFromParams(params: URLSearchParams): BranchListParams {
  return { search: params.get('search') ?? undefined, status: params.get('status') ?? undefined, city: params.get('city') ?? undefined, region: params.get('region') ?? undefined, manager_user_id: params.get('manager_user_id') ?? undefined, sort: params.get('sort') ?? 'created_at', direction: params.get('direction') ?? 'desc', page: Number(params.get('page') ?? 1), per_page: Number(params.get('per_page') ?? 25) };
}

export function BranchManagement() {
  const [params, setParams] = useSearchParams();
  const queryClient = useQueryClient();
  const companies = useQuery({ queryKey: ['dashboard-companies', 'branches'], queryFn: getDashboardCompanies });
  const me = useQuery({ queryKey: ['me', 'branches'], queryFn: getMe, retry: false });
  const companyId = params.get('company_id') ?? companies.data?.data.data[0]?.id ?? '';
  const company = companies.data?.data.data.find((item) => item.id === companyId);
  const canManage = (me.data?.data.permissions ?? []).includes('organization.manage') || (me.data?.data.permissions ?? []).includes('company.manage');
  const filters = useMemo(() => filtersFromParams(params), [params]);
  const [formMode, setFormMode] = useState<'new' | Branch | null>(null);
  const branches = useQuery({ queryKey: ['branches', companyId, filters], queryFn: () => getBranches(companyId, filters), enabled: Boolean(companyId) });
  const managers = useQuery({ queryKey: ['branch-manager-candidates', companyId], queryFn: () => getBranchManagerCandidates(companyId), enabled: Boolean(companyId) });
  const staff = useQuery({ queryKey: ['branch-staff-candidates', companyId], queryFn: () => getBranchStaffCandidates(companyId), enabled: Boolean(companyId) });
  const save = useMutation({ mutationFn: ({ data, branch }: { data: BranchInput; branch: Branch | null }) => branch ? updateBranch(companyId, branch.id, Object.fromEntries(Object.entries(data).filter(([key]) => key !== 'status')) as Partial<BranchInput>) : createBranch(companyId, data), onSuccess: () => { setFormMode(null); void queryClient.invalidateQueries({ queryKey: ['branches', companyId] }); } });
  const status = useMutation({ mutationFn: ({ branch, next }: { branch: Branch; next: Branch['status'] }) => updateBranchStatus(companyId, branch.id, next), onSuccess: () => { void queryClient.invalidateQueries({ queryKey: ['branches', companyId] }); } });
  const updateFilters = (next: BranchListParams) => { const nextParams = new URLSearchParams(params); Object.entries(next).forEach(([key, value]) => { if (value === undefined || value === '') nextParams.delete(key); else nextParams.set(key, String(value)); }); nextParams.set('company_id', companyId); setParams(nextParams, { replace: true }); };
  const resetFilters = () => setParams(companyId ? { company_id: companyId, page: '1', per_page: String(filters.per_page ?? 25) } : {}, { replace: true });
  const submit = (data: BranchInput) => save.mutate({ data, branch: formMode && formMode !== 'new' ? formMode : null });
  const page = branches.data?.data;
  if (companies.isLoading) return <section className="page-section organization-page branch-management-page"><div className="dashboard-loading" role="status">Loading authorized companies…</div></section>;
  if (companies.isError) return <section className="page-section organization-page branch-management-page"><div className="organization-state-card card"><CircleAlert size={24} /><h2>Branch management unavailable</h2><p className="muted">{companies.error instanceof ApiError && companies.error.status === 403 ? 'Your account does not have access to an active company.' : companies.error.message}</p><button className="secondary" type="button" onClick={() => companies.refetch()}><RefreshCw size={14} />Retry</button></div></section>;
  if (!companyId) return <section className="page-section organization-page branch-management-page"><div className="organization-state-card card"><Building2 size={24} /><h2>{(companies.data?.meta?.inactive_count ?? 0) > 0 ? 'Your company is inactive' : 'Set up your company'}</h2><p className="muted">{(companies.data?.meta?.inactive_count ?? 0) > 0 ? 'An inactive company cannot manage branches. Ask an authorized administrator to review its status.' : 'Create an active company before adding branches.'}</p>{(companies.data?.meta?.inactive_count ?? 0) === 0 && <Link className="primary" to="/company">Open company setup</Link>}</div></section>;
  return <section className="page-section organization-page branch-management-page">
    <div className="page-heading"><div><div className="breadcrumb-line"><Link to={`/organization?company_id=${companyId}`}>Organization</Link><ChevronRight size={13} /><span>Branches</span></div><h1>Branch management</h1><p className="muted">Manage authorized company locations, managers, staff, and operational status.</p></div><div className="branch-heading-actions">{company && <label className="context-select">Company<select aria-label="Select branch company" value={companyId} onChange={(event) => setParams({ company_id: event.target.value, page: '1' }, { replace: true })}>{companies.data?.data.data.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>}{canManage && <button className="primary" type="button" onClick={() => setFormMode('new')} disabled={!company}><Plus size={15} /> Add branch</button>}</div></div>
    {!canManage && <div className="alert alert-warning" role="status">You have read-only access to this branch directory. Create and status actions are hidden.</div>}
    {company && <>
      <BranchSummaryCards summary={branches.data?.summary} isLoading={branches.isLoading} />
      <BranchFilters filters={filters} managers={managers.data?.data ?? []} onChange={updateFilters} onReset={resetFilters} />
      {(branches.isError || save.isError || status.isError) && <div className="alert alert-danger" role="alert">{branches.error?.message || save.error?.message || status.error?.message}<button className="table-action" type="button" onClick={() => { void branches.refetch(); save.reset(); status.reset(); }}>Retry</button></div>}
      {branches.isLoading ? <div className="dashboard-loading" role="status">Loading branches…</div> : <BranchTable branches={page?.data ?? []} companyName={company.name} canManage={canManage} onEdit={(branch) => setFormMode(branch)} onToggle={(branch) => { const next = branch.status === 'active' ? 'inactive' : 'active'; if (window.confirm(`${next === 'active' ? 'Activate' : 'Deactivate'} ${branch.name}?`)) status.mutate({ branch, next }); }} onStatus={(branch, next) => { if (window.confirm(`Archive ${branch.name}? Historical references will be retained.`)) status.mutate({ branch, next }); }} />}
      <div className="branch-pagination"><label>Rows per page<select value={filters.per_page ?? 25} onChange={(event) => updateFilters({ ...filters, per_page: Number(event.target.value), page: 1 })}><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label><span>{page ? `Page ${page.current_page} of ${page.last_page} · ${page.total} branches` : ''}</span><div><button className="secondary" type="button" disabled={!page || page.current_page <= 1} onClick={() => updateFilters({ ...filters, page: (page?.current_page ?? 1) - 1 })}>Previous</button><button className="secondary" type="button" disabled={!page || page.current_page >= page.last_page} onClick={() => updateFilters({ ...filters, page: (page?.current_page ?? 1) + 1 })}>Next</button></div></div>
    </>}
    {formMode && company && <div className="branch-form-overlay"><div className="branch-form-dialog"><BranchForm branch={formMode === 'new' ? null : formMode} managers={managers.data?.data ?? []} staff={staff.data?.data ?? []} isSaving={save.isPending} error={save.error?.message} onSubmit={submit} onCancel={() => { setFormMode(null); save.reset(); }} /></div></div>}
    {branches.isFetching && !branches.isLoading && <RefreshCw className="branch-refresh-indicator" size={15} aria-label="Refreshing branches" />}
  </section>;
}
