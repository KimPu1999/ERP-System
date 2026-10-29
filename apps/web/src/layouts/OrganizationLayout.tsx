import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Building2, ChevronRight, Landmark, Network, ShieldCheck, Users, Warehouse, type LucideIcon } from 'lucide-react';
import { NavLink, Outlet, useSearchParams } from 'react-router-dom';
import { getDashboardCompanies, getMe } from '../lib/api';

type OrganizationNavItem = { label: string; to: string; permission: string; icon: LucideIcon };

const navItems: OrganizationNavItem[] = [
  { label: 'Overview', to: '/organization', permission: 'overview', icon: Landmark },
  { label: 'Company Profile', to: '/organization/profile', permission: 'profile', icon: Building2 },
  { label: 'Branches', to: '/organization/branches', permission: 'organization.manage', icon: Warehouse },
  { label: 'Departments', to: '/organization/departments', permission: 'organization.manage', icon: Network },
  { label: 'Organization Structure', to: '/organization/structure', permission: 'structure', icon: Network },
  { label: 'Staff & Memberships', to: '/organization/members', permission: 'members.invite', icon: Users },
  { label: 'Roles & Permissions', to: '/organization/roles', permission: 'roles.manage', icon: ShieldCheck },
];

export function OrganizationLayout() {
  const [searchParams, setSearchParams] = useSearchParams();
  const companies = useQuery({ queryKey: ['dashboard-companies', 'organization'], queryFn: getDashboardCompanies });
  const permissions = useQuery({ queryKey: ['me', 'organization'], queryFn: getMe, retry: false });
  const companyList = companies.data?.data.data ?? [];
  const selectedCompanyId = searchParams.get('company_id');
  const companyId = selectedCompanyId ?? companyList[0]?.id ?? '';
  const granted = permissions.data?.data.permissions ?? [];

  useEffect(() => {
    if (!selectedCompanyId && companyId) {
      const next = new URLSearchParams(searchParams);
      next.set('company_id', companyId);
      setSearchParams(next, { replace: true });
    }
  }, [companyId, searchParams, selectedCompanyId, setSearchParams]);

  const allowed = (permission: string) => {
    if (permission === 'overview' || permission === 'structure') return true;
    if (permissions.isLoading) return true;
    if (permission === 'profile') return granted.includes('company.manage') || granted.includes('organization.manage');
    return granted.includes(permission) || granted.includes('organization.manage');
  };

  const withCompanyContext = (to: string) => `${to}${companyId ? `?company_id=${encodeURIComponent(companyId)}` : ''}`;

  return <div className="organization-layout">
    <header className="organization-layout-heading">
      <div>
        <div className="breadcrumb-line"><span>Workspace</span><ChevronRight size={13} /><span>Organization</span></div>
        <h1>Organization</h1>
        <p className="muted">Manage your company identity, structure, people, and access in one workspace.</p>
      </div>
      {companyId && <span className="organization-context-chip"><Building2 size={14} />{companyList.find((company) => company.id === companyId)?.name ?? 'Selected company'}</span>}
    </header>
    <nav className="organization-nav" aria-label="Organization navigation">
      <div className="organization-nav-inner">
        {navItems.filter((item) => allowed(item.permission)).map(({ label, to, permission, icon: Icon }) => <NavLink key={to} end={permission === 'overview'} to={withCompanyContext(to)} className={({ isActive }) => `organization-nav-link ${isActive ? 'active' : ''}`}><Icon size={14} aria-hidden="true" /><span>{label}</span></NavLink>)}
      </div>
    </nav>
    <Outlet />
  </div>;
}
