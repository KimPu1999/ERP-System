import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Building2, Landmark, Network, ShieldCheck, Users, Warehouse, type LucideIcon } from 'lucide-react';
import { NavLink, Outlet, useSearchParams } from 'react-router-dom';
import { getDashboardCompanies, getMe } from '../lib/api';
import { useI18n, type TranslationKey } from '../lib/i18n';
import { ScrollableTabs } from '../components/ScrollableTabs';

type OrganizationNavItem = { label: TranslationKey; to: string; permission: string; icon: LucideIcon };

const navItems: OrganizationNavItem[] = [
  { label: 'org.overview', to: '/organization', permission: 'overview', icon: Landmark },
  { label: 'org.profile', to: '/organization/profile', permission: 'profile', icon: Building2 },
  { label: 'org.branches', to: '/organization/branches', permission: 'organization.manage', icon: Warehouse },
  { label: 'org.departments', to: '/organization/departments', permission: 'organization.manage', icon: Network },
  { label: 'org.structure', to: '/organization/structure', permission: 'structure', icon: Network },
  { label: 'org.members', to: '/organization/members', permission: 'members.invite', icon: Users },
  { label: 'org.roles', to: '/organization/roles', permission: 'roles.manage', icon: ShieldCheck },
];

export function OrganizationLayout() {
  const { t } = useI18n();
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
    <nav className="organization-nav" aria-label={t('nav.organization')}>
      <ScrollableTabs className="organization-nav-inner" label={t('nav.organization')}>
        {navItems.filter((item) => allowed(item.permission)).map(({ label, to, permission, icon: Icon }) => <NavLink key={to} end={permission === 'overview'} to={withCompanyContext(to)} className={({ isActive }) => `organization-nav-link ${isActive ? 'active' : ''}`}><Icon size={14} aria-hidden="true" /><span>{t(label)}</span></NavLink>)}
      </ScrollableTabs>
    </nav>
    <Outlet />
  </div>;
}
