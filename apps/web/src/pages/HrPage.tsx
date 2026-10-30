import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BriefcaseBusiness, CheckCircle2, CircleUserRound, Plus, RefreshCw, Search, Users, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { createEmployee, getBranches, getCompanies, getDepartments, getEmployees, getMe, getPayrollRuns, type Branch, type Company, type Department, type Employee } from '../lib/api';

const pageSizes = [10, 25, 50, 100] as const;

function statusClass(status: string) {
  return status === 'active' ? 'status-pill success' : status === 'inactive' ? 'status-pill warning' : 'status-pill';
}

function EmployeeForm({ companies, branches, departments, defaultCompany, onCancel, onSaved }: { companies: Company[]; branches: Branch[]; departments: Department[]; defaultCompany: string; onCancel: () => void; onSaved: () => void }) {
  const client = useQueryClient();
  const [values, setValues] = useState({ company_id: defaultCompany, employee_number: '', legal_name: '', preferred_name: '', email: '', phone: '', joined_on: new Date().toISOString().slice(0, 10), branch_id: '', department_id: '' });
  const mutation = useMutation({ mutationFn: () => createEmployee({ ...values, branch_id: values.branch_id || null, department_id: values.department_id || null }), onSuccess: () => { void client.invalidateQueries({ queryKey: ['hr-employees'] }); onSaved(); } });
  const set = (key: keyof typeof values, value: string) => setValues((current) => ({ ...current, [key]: value }));
  return <form className="card hr-employee-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
    <div className="hr-form-heading"><div><h2>Add employee</h2><span>Only the employee record is created. ERP login access is not granted automatically.</span></div><button className="icon-button" type="button" aria-label="Close employee form" onClick={onCancel}><X size={16} /></button></div>
    <div className="form-grid">
      <label>Company <span className="required-label">Required</span><select value={values.company_id} onChange={(event) => set('company_id', event.target.value)} required><option value="">Select company</option>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label>
      <label>Employee number <span className="required-label">Required</span><input value={values.employee_number} onChange={(event) => set('employee_number', event.target.value)} required /></label>
      <label>Legal name <span className="required-label">Required</span><input value={values.legal_name} onChange={(event) => set('legal_name', event.target.value)} required /></label>
      <label>Preferred name <span className="optional-label">Optional</span><input value={values.preferred_name} onChange={(event) => set('preferred_name', event.target.value)} /></label>
      <label>Email <span className="optional-label">Optional</span><input type="email" value={values.email} onChange={(event) => set('email', event.target.value)} /></label>
      <label>Phone <span className="optional-label">Optional</span><input value={values.phone} onChange={(event) => set('phone', event.target.value)} /></label>
      <label>Joined date <span className="required-label">Required</span><input type="date" value={values.joined_on} onChange={(event) => set('joined_on', event.target.value)} required /></label>
      <label>Branch <span className="optional-label">Optional</span><select value={values.branch_id} onChange={(event) => set('branch_id', event.target.value)}><option value="">Unassigned</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
      <label>Department <span className="optional-label">Optional</span><select value={values.department_id} onChange={(event) => set('department_id', event.target.value)}><option value="">Unassigned</option>{departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}</select></label>
    </div>
    {mutation.isError && <div className="alert alert-danger" role="alert">Unable to create employee. {mutation.error.message}</div>}
    <div className="profile-actions"><button className="primary" type="submit" disabled={mutation.isPending || !values.company_id}>{mutation.isPending ? 'Saving…' : 'Save employee'}</button><button className="secondary" type="button" onClick={onCancel}>Cancel</button></div>
  </form>;
}

export function HrPage() {
  const [params, setParams] = useSearchParams();
  const [showForm, setShowForm] = useState(false);
  const search = params.get('search') ?? '';
  const status = params.get('status') ?? '';
  const sort = params.get('sort') ?? 'legal_name';
  const direction = params.get('direction') === 'desc' ? 'desc' as const : 'asc' as const;
  const page = Number(params.get('page') ?? 1);
  const perPage = pageSizes.includes(Number(params.get('per_page')) as (typeof pageSizes)[number]) ? Number(params.get('per_page')) as (typeof pageSizes)[number] : 25;
  const companyId = params.get('company_id') ?? '';
  const companies = useQuery({ queryKey: ['hr-companies'], queryFn: getCompanies });
  const companyRows = companies.data?.data.data ?? [];
  const selectedCompany = companyId || (companyRows.length === 1 ? companyRows[0].id : '');
  const branches = useQuery({ queryKey: ['hr-branches', selectedCompany], queryFn: () => getBranches(selectedCompany, { status: 'active', per_page: 100 }), enabled: Boolean(selectedCompany) });
  const departments = useQuery({ queryKey: ['hr-departments', selectedCompany], queryFn: () => getDepartments({ company_id: selectedCompany, status: 'active', per_page: 100 }), enabled: Boolean(selectedCompany) });
  const employees = useQuery({ queryKey: ['hr-employees', { companyId: selectedCompany, search, status, sort, direction, page, perPage }], queryFn: () => getEmployees({ company_id: selectedCompany || undefined, search: search || undefined, status: status || undefined, sort, direction, page, per_page: perPage }) });
  const payroll = useQuery({ queryKey: ['hr-payroll-runs', selectedCompany], queryFn: () => getPayrollRuns(selectedCompany || undefined) });
  const me = useQuery({ queryKey: ['me'], queryFn: getMe });
  const canManage = me.data?.data.permissions.includes('hr.manage') ?? false;
  const employeeData = employees.data?.data;
  const rows = employeeData?.data ?? [];
  const payrollRows = payroll.data?.data.data ?? [];
  const error = employees.error ?? payroll.error;
  const loading = employees.isLoading || payroll.isLoading;
  const updateFilters = (updates: Record<string, string | undefined>) => { const next = new URLSearchParams(params); Object.entries(updates).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key)); setParams(next, { replace: true }); };
  const setFilter = (key: string, value: string) => updateFilters({ [key]: value, page: undefined });
  const clearFilters = () => { const next = new URLSearchParams(params); ['search', 'status', 'branch_id', 'department_id', 'sort', 'direction', 'page', 'per_page'].forEach((key) => next.delete(key)); setParams(next, { replace: true }); };
  const refresh = () => { void employees.refetch(); void payroll.refetch(); void companies.refetch(); };
  const selectedBranches = branches.data?.data.data ?? [];
  const selectedDepartments = departments.data?.data.data ?? [];
  const summary = employees.data?.summary;
  const hasFilters = Boolean(search || status || params.get('per_page') || (sort !== 'legal_name' || direction !== 'asc'));
  const sortValue = `${sort}:${direction}`;
  const pageCount = employeeData?.last_page ?? 1;
  const pages = useMemo(() => Array.from({ length: Math.min(pageCount, 5) }, (_, index) => Math.max(1, Math.min(pageCount - 4, page)) + index).filter((value) => value <= pageCount), [page, pageCount]);

  return <section className="page-section hr-page">
    <header className="hr-page-header"><div><h1>People & payroll</h1><span>Employees and payroll records for authorized companies.</span></div><div className="hr-header-actions">{canManage && <button className="primary" type="button" onClick={() => setShowForm((value) => !value)}><Plus size={15} /> Add employee</button>}<button className="icon-button" type="button" aria-label="Refresh people and payroll" onClick={refresh} disabled={loading}><RefreshCw size={16} className={loading ? 'hr-spin' : undefined} /></button></div></header>
    <div className="hr-filter-toolbar"><label className="hr-search"><Search size={15} /><span className="sr-only">Search employees</span><input value={search} onChange={(event) => setFilter('search', event.target.value)} placeholder="Search name or employee number" /></label><label><span>Company</span><select value={selectedCompany} onChange={(event) => setFilter('company_id', event.target.value)}><option value="">All authorized companies</option>{companyRows.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label><label><span>Status</span><select value={status} onChange={(event) => setFilter('status', event.target.value)}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="ended">Ended</option></select></label><label><span>Sort</span><select value={sortValue} onChange={(event) => { const [nextSort, nextDirection] = event.target.value.split(':'); updateFilters({ sort: nextSort, direction: nextDirection, page: undefined }); }}><option value="legal_name:asc">Name A–Z</option><option value="legal_name:desc">Name Z–A</option><option value="joined_on:desc">Newest joined</option><option value="status:asc">Status</option></select></label><button className="secondary" type="button" disabled={!hasFilters} onClick={clearFilters}>Clear</button></div>
    {showForm && canManage && <EmployeeForm companies={companyRows} branches={selectedBranches} departments={selectedDepartments} defaultCompany={selectedCompany} onCancel={() => setShowForm(false)} onSaved={() => setShowForm(false)} />}
    {error && <div className="alert alert-danger" role="alert">Unable to load People & Payroll data. {error instanceof Error ? error.message : 'Request failed.'} <button className="table-action" type="button" onClick={refresh}>Retry</button></div>}
    <div className="hr-kpi-grid"><div className="hr-kpi"><span><Users size={15} /> Total employees</span><strong>{loading ? '…' : summary?.total ?? 0}</strong></div><div className="hr-kpi accent-blue"><span><CircleUserRound size={15} /> Active</span><strong>{loading ? '…' : summary?.active ?? 0}</strong></div><div className="hr-kpi accent-orange"><span><CircleUserRound size={15} /> Inactive</span><strong>{loading ? '…' : summary?.inactive ?? 0}</strong></div><div className="hr-kpi accent-purple"><span><BriefcaseBusiness size={15} /> Payroll runs</span><strong>{loading ? '…' : payroll.data?.summary.total ?? 0}</strong></div><div className="hr-kpi accent-green"><span><CheckCircle2 size={15} /> Finalized</span><strong>{loading ? '…' : payroll.data?.summary.finalized ?? 0}</strong></div></div>
    <article className="table-card hr-directory"><div className="table-card-header"><div><h2>Employee directory</h2><span>{employeeData ? `${employeeData.total} records` : loading ? 'Loading…' : 'No records'}</span></div><label className="hr-rows"><span>Rows</span><select value={perPage} onChange={(event) => setFilter('per_page', event.target.value)}>{pageSizes.map((size) => <option key={size} value={size}>{size}</option>)}</select></label></div><div className="table-scroll"><table><thead><tr><th>Employee</th><th>Employee ID</th><th>Department</th><th>Branch</th><th>Joined</th><th>Status</th></tr></thead><tbody>{loading ? <tr><td colSpan={6}><div className="hr-skeleton"><span /><span /><span /><span /></div></td></tr> : rows.map((employee: Employee) => <tr key={employee.id}><td><strong>{employee.preferred_name || employee.legal_name}</strong>{employee.preferred_name && <small>{employee.legal_name}</small>}</td><td>{employee.employee_number}</td><td>{employee.department?.name ?? 'Unassigned'}</td><td>{employee.branch?.name ?? 'Unassigned'}</td><td>{employee.joined_on}</td><td><span className={statusClass(employee.status)}>{employee.status}</span></td></tr>)}{!loading && !error && rows.length === 0 && <tr><td colSpan={6}><div className="hr-empty"><Users size={22} /><strong>No employee records</strong><span>{search || status ? 'Try clearing the filters.' : 'Authorized employee records will appear here.'}</span>{canManage && !showForm && <button className="primary" type="button" onClick={() => setShowForm(true)}><Plus size={14} /> Add employee</button>}</div></td></tr>}</tbody></table></div>{pageCount > 1 && <div className="pagination"><button className="secondary" type="button" disabled={page <= 1} onClick={() => setFilter('page', String(page - 1))}>Previous</button>{pages.map((value) => <button className={value === page ? 'active' : ''} type="button" key={value} onClick={() => setFilter('page', String(value))}>{value}</button>)}<button className="secondary" type="button" disabled={page >= pageCount} onClick={() => setFilter('page', String(page + 1))}>Next</button></div>}</article>
    <article className="table-card hr-payroll"><div className="table-card-header"><div><h2>Payroll runs</h2><span>{payrollRows.length ? `${payroll.data?.summary.total ?? payrollRows.length} records` : 'No records'}</span></div></div><div className="table-scroll"><table><thead><tr><th>Run</th><th>Period</th><th>Employees</th><th>State</th></tr></thead><tbody>{payroll.isLoading ? <tr><td colSpan={4}>Loading payroll runs…</td></tr> : payrollRows.map((run) => <tr key={run.id}><td><strong>{run.name}</strong></td><td>{run.period_starts_on} – {run.period_ends_on}</td><td>{run.items?.length ?? 0}</td><td><span className={run.state === 'finalized' ? 'status-pill success' : 'status-pill'}>{run.state}</span></td></tr>)}{!payroll.isLoading && payrollRows.length === 0 && <tr><td colSpan={4} className="muted">No payroll runs are available for the selected scope.</td></tr>}</tbody></table></div></article>
  </section>;
}
