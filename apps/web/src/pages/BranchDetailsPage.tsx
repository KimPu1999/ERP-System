import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams, useSearchParams } from 'react-router-dom';
import { BranchDetails } from '../components/BranchDetails';
import { BranchForm } from '../components/BranchForm';
import { getBranch, getBranchManagerCandidates, getBranchStaffCandidates, updateBranch, updateBranchStatus, type Branch, type BranchInput } from '../lib/api';
import { useState } from 'react';

export function BranchDetailsPage() {
  const { branchId = '' } = useParams();
  const [params] = useSearchParams();
  const companyId = params.get('company_id') ?? '';
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const details = useQuery({ queryKey: ['branch', companyId, branchId], queryFn: () => getBranch(companyId, branchId), enabled: Boolean(companyId && branchId) });
  const managers = useQuery({ queryKey: ['branch-manager-candidates', companyId], queryFn: () => getBranchManagerCandidates(companyId), enabled: Boolean(companyId) });
  const staff = useQuery({ queryKey: ['branch-staff-candidates', companyId], queryFn: () => getBranchStaffCandidates(companyId), enabled: Boolean(companyId) });
  const save = useMutation({ mutationFn: (data: BranchInput) => updateBranch(companyId, branchId, Object.fromEntries(Object.entries(data).filter(([key]) => key !== 'status')) as Partial<BranchInput>), onSuccess: () => { setEditing(false); void queryClient.invalidateQueries({ queryKey: ['branch', companyId, branchId] }); void queryClient.invalidateQueries({ queryKey: ['branches', companyId] }); } });
  const status = useMutation({ mutationFn: (next: Branch['status']) => updateBranchStatus(companyId, branchId, next), onSuccess: () => { void queryClient.invalidateQueries({ queryKey: ['branch', companyId, branchId] }); void queryClient.invalidateQueries({ queryKey: ['branches', companyId] }); } });
  if (details.isLoading) return <section className="page-section"><div className="dashboard-loading">Loading branch details…</div></section>;
  if (details.isError || !details.data?.data) return <section className="page-section"><div className="alert alert-danger" role="alert">Unable to load branch details. {details.error?.message}<button className="table-action" type="button" onClick={() => void details.refetch()}>Retry</button></div></section>;
  if (editing) return <section className="page-section organization-page"><BranchForm branch={details.data.data.branch} managers={managers.data?.data ?? []} staff={staff.data?.data ?? []} isSaving={save.isPending} error={save.error?.message} onSubmit={(data) => save.mutate(data)} onCancel={() => { setEditing(false); save.reset(); }} /></section>;
  return <section className="page-section organization-page">{(status.isError || status.isSuccess) && <div className={`alert ${status.isError ? 'alert-danger' : 'alert-success'}`} role="status">{status.error?.message ?? 'Branch status updated.'}</div>}<BranchDetails data={details.data.data} onEdit={() => setEditing(true)} onStatus={(next) => { if (next === 'archived' && !window.confirm('Archive this branch? Historical references will be retained.')) return; status.mutate(next); }} isSaving={status.isPending} /></section>;
}
