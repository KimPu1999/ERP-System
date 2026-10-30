import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { getEmployees, getPayrollRuns } from '../lib/api';

export function HrPage() {
  const employees = useQuery({ queryKey: ['hr-employees'], queryFn: () => getEmployees() });
  const payroll = useQuery({ queryKey: ['payroll-runs'], queryFn: getPayrollRuns });
  const error = (employees.error ?? payroll.error) as Error | null;
  const loading = employees.isLoading || payroll.isLoading;
  const employeeRows = employees.data?.data.data ?? [];
  const payrollRows = payroll.data?.data.data ?? [];
  const refresh = () => { void employees.refetch(); void payroll.refetch(); };
  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">Human Resources</p><h1>People & payroll</h1><p className="muted">Protected employee records, configurable policies and controlled payroll finalization.</p></div><button className="icon-button" type="button" aria-label="Refresh people and payroll" onClick={refresh}><RefreshCw size={16} /></button></div>{error && <div className="alert alert-danger" role="alert">Unable to load People & Payroll data. <button className="table-action" type="button" onClick={refresh}>Retry</button></div>}<div className="metric-grid"><div className="metric"><span>Employees</span><strong>{loading ? '…' : employeeRows.length}</strong></div><div className="metric"><span>Payroll runs</span><strong>{loading ? '…' : payrollRows.length}</strong></div><div className="metric"><span>Finalized runs</span><strong>{loading ? '…' : payrollRows.filter((run) => run.state === 'finalized').length}</strong></div></div><div className="panel"><div className="table-card-header"><div><h2>Employee directory</h2><p className="muted">Authorized employee records for the active tenant.</p></div><span className="status-pill">{employeeRows.length} records</span></div>{loading && <div className="empty">Loading employee records…</div>}{!loading && !error && employeeRows.length === 0 && <div className="empty">No employee records are available to this account.</div>}{!loading && !error && employeeRows.length > 0 && <div className="table-scroll"><table><thead><tr><th>Number</th><th>Name</th><th>Joined</th><th>Status</th></tr></thead><tbody>{employeeRows.map((employee) => <tr key={employee.id}><td>{employee.employee_number}</td><td>{employee.preferred_name || employee.legal_name}</td><td>{employee.joined_on}</td><td><span className="badge">{employee.status}</span></td></tr>)}</tbody></table></div>}</div></section>;
}
