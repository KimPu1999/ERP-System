import { useQuery } from '@tanstack/react-query';
import { getEmployees, getPayrollRuns } from '../lib/api';

export function HrPage() {
  const employees = useQuery({ queryKey: ['hr-employees'], queryFn: () => getEmployees() });
  const payroll = useQuery({ queryKey: ['payroll-runs'], queryFn: getPayrollRuns });
  const error = (employees.error ?? payroll.error) as Error | null;
  return <section><p className="eyebrow">Human Resources</p><h1>People & payroll</h1><p className="muted">Protected employee records, configurable policies and controlled payroll finalization.</p>{error && <div className="empty error">{error.message}</div>}<div className="metric-grid"><div className="metric"><span>Employees</span><strong>{employees.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Payroll runs</span><strong>{payroll.data?.data.data.length ?? '—'}</strong></div><div className="metric"><span>Finalized runs</span><strong>{payroll.data?.data.data.filter((run) => run.state === 'finalized').length ?? '—'}</strong></div></div><div className="panel"><h2>Employee directory</h2><table><thead><tr><th>Number</th><th>Name</th><th>Joined</th><th>Status</th></tr></thead><tbody>{employees.data?.data.data.map((employee) => <tr key={employee.id}><td>{employee.employee_number}</td><td>{employee.preferred_name || employee.legal_name}</td><td>{employee.joined_on}</td><td><span className="badge">{employee.status}</span></td></tr>)}</tbody></table>{employees.data?.data.data.length === 0 && <div className="empty">No employee records are available to this account.</div>}</div></section>;
}
