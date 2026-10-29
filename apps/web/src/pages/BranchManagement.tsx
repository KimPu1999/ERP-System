import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { BranchFilters } from '../components/BranchFilters';
import { BranchForm } from '../components/BranchForm';
import { BranchSummaryCards } from '../components/BranchSummaryCards';
import { BranchTable } from '../components/BranchTable';
import { createBranch, getBranchManagerCandidates, getBranchStaffCandidates, getBranches, getCompanies, updateBranch, updateBranchStatus, type Branch, type BranchInput, type BranchListParams } from '../lib/api';

function filtersFromParams(params: URLSearchParams): BranchListParams {
  return { search: params.get('search') ?? undefined, status: params.get('status') ?? undefined, city: params.get('city') ?? undefined, region: params.get('region') ?? undefined, manager_user_id: params.get('manager_user_id') ?? undefined, sort: params.get('sort') ?? 'created_at', direction: params.get('direction') ?? 'desc', page: Number(params.get('page') ?? 1), per_page: Number(params.get('per_page') ?? 25) };
}

export function BranchManagement() {
  const [params, setParams] = useSearchParams();
  const queryClient = useQueryClient();
  const companies = useQuery({ queryKey: ['companies', 'branches'], queryFn: getCompanies });
  const companyId = params.get('company_id') ?? companies.data?.data.data[0]?.id ?? '';
  const company = companies.data?.data.data.find((item) => item.id === companyId);
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
  return <section className="page-section organization-page branch-management-page">
    <div className="page-heading"><div><p className="eyebrow">Organization · Branches</p><h1>Branch management</h1><p className="muted">Manage authorized company locations, managers, staff, and operational status.</p></div><div className="branch-heading-actions">{company && <label className="context-select">Company<select aria-label="Select branch company" value={companyId} onChange={(event) => setParams({ company_id: event.target.value, page: '1' }, { replace: true })}>{companies.data?.data.data.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>}<button className="primary" type="button" onClick={() => setFormMode('new')} disabled={!company}><Plus size={15} /> Add branch</button></div></div>
    {!company && <div className="empty-state card"><h2>No authorized company</h2><p className="muted">Select an active company with organization access before managing branches.</p><Link className="primary inline-action" to="/organization">Organization overview</Link></div>}
    {company && <>
      <BranchSummaryCards summary={branches.data?.summary} isLoading={branches.isLoading} />
      <BranchFilters filters={filters} managers={managers.data?.data ?? []} onChange={updateFilters} onReset={resetFilters} />
      {(branches.isError || save.isError || status.isError) && <div className="alert alert-danger" role="alert">{branches.error?.message || save.error?.message || status.error?.message}<button className="table-action" type="button" onClick={() => { void branches.refetch(); save.reset(); status.reset(); }}>Retry</button></div>}
      {branches.isLoading ? <div className="dashboard-loading" role="status">Loading branches…</div> : <BranchTable branches={page?.data ?? []} companyName={company.name} onEdit={(branch) => setFormMode(branch)} onToggle={(branch) => { const next = branch.status === 'active' ? 'inactive' : 'active'; if (window.confirm(`${next === 'active' ? 'Activate' : 'Deactivate'} ${branch.name}?`)) status.mutate({ branch, next }); }} />}
      <div className="branch-pagination"><label>Rows per page<select value={filters.per_page ?? 25} onChange={(event) => updateFilters({ ...filters, per_page: Number(event.target.value), page: 1 })}><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label><span>{page ? `Page ${page.current_page} of ${page.last_page} · ${page.total} branches` : ''}</span><div><button className="secondary" type="button" disabled={!page || page.current_page <= 1} onClick={() => updateFilters({ ...filters, page: (page?.current_page ?? 1) - 1 })}>Previous</button><button className="secondary" type="button" disabled={!page || page.current_page >= page.last_page} onClick={() => updateFilters({ ...filters, page: (page?.current_page ?? 1) + 1 })}>Next</button></div></div>
    </>}
    {formMode && company && <div className="branch-form-overlay"><div className="branch-form-dialog"><BranchForm branch={formMode === 'new' ? null : formMode} managers={managers.data?.data ?? []} staff={staff.data?.data ?? []} isSaving={save.isPending} error={save.error?.message} onSubmit={submit} onCancel={() => { setFormMode(null); save.reset(); }} /></div></div>}
    {branches.isFetching && !branches.isLoading && <RefreshCw className="branch-refresh-indicator" size={15} aria-label="Refreshing branches" />}
  </section>;
}
