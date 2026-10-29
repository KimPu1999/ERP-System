import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Building2, ChevronRight, ClipboardList, CreditCard, FileText, HandCoins, Receipt, RotateCcw, ShoppingCart, Target, Truck, Users, type LucideIcon } from 'lucide-react';
import { NavLink, Outlet, useSearchParams } from 'react-router-dom';
import { getDashboardCompanies, getMe } from '../lib/api';

type SalesNavItem = { label: string; to?: string; icon: LucideIcon; permission?: string; unavailable?: string };

const salesNavItems: SalesNavItem[] = [
  { label: 'Overview', to: '/crm-sales', icon: BarChart3 },
  { label: 'Customers', icon: Users, unavailable: 'Customer directory UI is planned for Sales Phase 2.' },
  { label: 'Leads & Opportunities', icon: Target, unavailable: 'Lead and opportunity workflows are not implemented.' },
  { label: 'Quotations', icon: FileText, permission: 'sales.manage', unavailable: 'Quotation management UI is planned for Sales Phase 2.' },
  { label: 'Sales Orders', icon: ShoppingCart, permission: 'sales.manage', unavailable: 'Sales order management UI is planned for Sales Phase 2.' },
  { label: 'Deliveries', icon: Truck, permission: 'sales.manage', unavailable: 'Delivery management UI is planned for Sales Phase 3.' },
  { label: 'Invoices', icon: Receipt, permission: 'sales.manage', unavailable: 'Invoice management UI is planned for Sales Phase 3.' },
  { label: 'Payments', icon: CreditCard, permission: 'sales.manage', unavailable: 'Payment workflow UI is planned for Sales Phase 3.' },
  { label: 'Returns & Refunds', icon: RotateCcw, permission: 'sales.manage', unavailable: 'Returns and refund UI is planned for Sales Phase 3.' },
  { label: 'Reports', icon: ClipboardList, unavailable: 'A dedicated Sales Reports workspace is planned for a later phase.' },
  { label: 'Sales Settings', icon: HandCoins, unavailable: 'Sales settings UI is not implemented.' },
];

export function SalesWorkspaceLayout() {
  const [searchParams, setSearchParams] = useSearchParams();
  const companies = useQuery({ queryKey: ['dashboard-companies', 'sales-workspace'], queryFn: getDashboardCompanies });
  const permissions = useQuery({ queryKey: ['me', 'sales-workspace'], queryFn: getMe, retry: false });
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

  const permitted = (permission?: string) => !permission || permissions.isLoading || granted.includes(permission) || granted.includes('organization.manage');
  const href = (to: string) => `${to}${companyId ? `?company_id=${encodeURIComponent(companyId)}` : ''}`;

  return <div className="sales-workspace-layout">
    <header className="sales-layout-heading">
      <div><div className="breadcrumb-line"><span>Workspace</span><ChevronRight size={13} /><span>CRM & Sales</span></div><h1>Sales workspace</h1><p className="muted">A focused operating view for the verified order-to-cash data already available in this workspace.</p></div>
      {companyId && <span className="sales-context-chip"><Building2 size={14} />{companyList.find((company) => company.id === companyId)?.name ?? 'Selected company'}</span>}
    </header>
    <nav className="sales-nav" aria-label="Sales navigation"><div className="sales-nav-inner">
      {salesNavItems.filter((item) => permitted(item.permission)).map(({ label, to, icon: Icon, unavailable }) => to && !unavailable ? <NavLink key={label} to={href(to)} end={to === '/crm-sales'} className={({ isActive }) => `sales-nav-link ${isActive ? 'active' : ''}`}><Icon size={14} aria-hidden="true" /><span>{label}</span></NavLink> : <span key={label} className="sales-nav-link disabled" aria-disabled="true" title={unavailable}>{<Icon size={14} aria-hidden="true" />}<span>{label}</span><small>Planned</small></span>)}
    </div></nav>
    <Outlet />
  </div>;
}
