import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Building2, Landmark, Network, ShieldCheck, Users, Warehouse, type LucideIcon } from 'lucide-react';
import { NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { getDashboardCompanies, getMe } from '../lib/api';
import { useI18n, type TranslationKey } from '../lib/i18n';

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
  const location = useLocation();
  const navigate = useNavigate();
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
  const selectedCompany = companyList.find((company) => company.id === companyId);
  const selectCompany = (value: string) => {
    const next = new URLSearchParams(searchParams);
    if (value) next.set('company_id', value); else next.delete('company_id');
    setSearchParams(next, { replace: true });
  };
  const goBack = () => {
    if (window.history.length > 1) navigate(-1);
    else navigate('/');
  };

  return <div className="organization-layout">
    <header className="organization-layout-heading">
      <div>
        <div className="organization-title-row"><button className="organization-back-button" type="button" onClick={goBack}><ArrowLeft size={15} />{t('org.back')}</button>{location.pathname === '/organization' ? <h1>{t('nav.organization')}</h1> : <span className="workspace-shell-label">{t('nav.organization')}</span>}</div>
      </div>
      {companyId && <div className="organization-layout-context"><label className="organization-context-selector"><Building2 size={14} /><span className="sr-only">{t('app.selectCompany')}</span><select aria-label={t('app.selectCompany')} value={companyId} onChange={(event) => selectCompany(event.target.value)}>{companyList.map((company) => <option value={company.id} key={company.id}>{company.name}</option>)}</select></label>{selectedCompany && <span className="organization-status-pill"><i />{selectedCompany.is_active ? t('common.active') : t('common.inactive')}</span>}</div>}
    </header>
    <nav className="organization-nav" aria-label={t('nav.organization')}>
      <div className="organization-nav-inner">
        {navItems.filter((item) => allowed(item.permission)).map(({ label, to, permission, icon: Icon }) => <NavLink key={to} end={permission === 'overview'} to={withCompanyContext(to)} className={({ isActive }) => `organization-nav-link ${isActive ? 'active' : ''}`}><Icon size={14} aria-hidden="true" /><span>{t(label)}</span></NavLink>)}
      </div>
    </nav>
    <Outlet />
  </div>;
}
