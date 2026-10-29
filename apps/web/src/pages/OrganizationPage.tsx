import { useQuery } from '@tanstack/react-query';
import { getCompanies } from '../lib/api';
export function OrganizationPage() { const companies = useQuery({ queryKey: ['companies'], queryFn: getCompanies }); return <section><p className="eyebrow">Tenant operations</p><h1>Company profile and organization</h1><div className="table-card"><h2>Companies</h2><table><tbody>{companies.data?.data.data.map((company) => <tr key={company.id}><td>{company.name}</td><td>{company.code}</td><td>{company.is_active ? 'Active' : 'Inactive'}</td></tr>)}</tbody></table></div></section>; }
