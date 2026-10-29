import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { createDepartment, getCompanies, getDepartments, type Department } from '../lib/api';

type Form = Pick<Department, 'name' | 'code' | 'company_id' | 'branch_id'>;
export function DepartmentManagementPage() {
  const [params] = useSearchParams();
  const client = useQueryClient();
  const companies = useQuery({ queryKey: ['companies', 'departments'], queryFn: getCompanies });
  const departments = useQuery({ queryKey: ['departments'], queryFn: getDepartments });
  const form = useForm<Form>({ defaultValues: { name: '', code: '', company_id: params.get('company_id') ?? '', branch_id: null } });
  const mutation = useMutation({ mutationFn: createDepartment, onSuccess: () => { form.reset({ name: '', code: '', company_id: form.getValues('company_id'), branch_id: null }); client.invalidateQueries({ queryKey: ['departments'] }); } });
  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">Organization · Departments</p><h1>Departments</h1><p className="muted">Keep department ownership and organization codes consistent.</p></div><Link className="secondary" to={`/organization?company_id=${params.get('company_id') ?? ''}`}>Back to overview</Link></div><div className="organization-grid"><form className="card form" onSubmit={form.handleSubmit((data) => mutation.mutate({ ...data, branch_id: data.branch_id || null }))}><h2>Add department</h2><label>Company<select {...form.register('company_id', { required: true })}><option value="">Select company</option>{companies.data?.data.data.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}</select></label><label>Department name<input {...form.register('name', { required: true })} /></label><label>Code<input {...form.register('code', { required: true, pattern: /^[A-Za-z0-9_-]+$/ })} /></label><button className="primary" type="submit" disabled={mutation.isPending}>{mutation.isPending ? 'Saving…' : 'Add department'}</button>{mutation.isError && <p className="error-text">{mutation.error.message}</p>}</form><div className="table-card"><h2>Configured departments</h2>{departments.isError && <p className="error-text">{departments.error.message}</p>}<table><thead><tr><th>Name</th><th>Code</th><th>Company</th></tr></thead><tbody>{departments.data?.data.data.map((department) => <tr key={department.id}><td>{department.name}</td><td>{department.code}</td><td>{companies.data?.data.data.find((company) => company.id === department.company_id)?.name ?? department.company_id}</td></tr>)}</tbody></table>{departments.data?.data.data.length === 0 && <p className="muted">No departments configured.</p>}</div></div></section>;
}
