import { useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { getMembers } from '../lib/api';

export function OrganizationMembersPage() {
  const [params] = useSearchParams();
  const members = useQuery({ queryKey: ['organization-members'], queryFn: getMembers });
  return <section className="page-section"><div className="page-heading"><div><p className="eyebrow">Organization · Staff & Memberships</p><h1>Staff and memberships</h1><p className="muted">Review active members in the current authorized workspace.</p></div><Link className="secondary" to={`/organization?company_id=${params.get('company_id') ?? ''}`}>Back to overview</Link></div><div className="table-card">{members.isError && <p className="error-text">Unable to load members. {members.error.message}</p>}<table><thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead><tbody>{members.data?.data.data.map((member) => <tr key={member.id}><td>{member.name}</td><td>{member.email}</td><td><span className="status-pill success">{member.status ?? 'active'}</span></td></tr>)}</tbody></table>{members.data?.data.data.length === 0 && <p className="muted">No active members found.</p>}</div></section>;
}
