import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Banknote, ClipboardCheck, ClipboardList, FileCheck2, FileMinus2, PackageCheck, Receipt, RotateCcw, Settings2, ShoppingCart, Truck, Users, type LucideIcon } from 'lucide-react';
import { NavLink, Outlet, useSearchParams } from 'react-router-dom';
import { getDashboardCompanies, getMe } from '../lib/api';

type PurchasingNavItem = { label: string; to?: string; icon: LucideIcon; unavailable?: string };

const purchasingNavItems: PurchasingNavItem[] = [
  { label: 'Overview', to: '/purchasing', icon: BarChart3 },
  { label: 'Suppliers', to: '/purchasing/suppliers', icon: Users },
  { label: 'Supplier Onboarding', to: '/purchasing/suppliers/onboarding', icon: FileCheck2 },
  { label: 'Supplier Financials', to: '/purchasing/suppliers/financial', icon: Banknote },
  { label: 'Purchase Requests', icon: ClipboardList, unavailable: 'Purchase request management UI is planned for the next purchasing phase.' },
  { label: 'Purchase Orders', to: '/purchasing', icon: ShoppingCart },
  { label: 'Purchase Approvals', to: '/purchasing/approvals', icon: ClipboardCheck },
  { label: 'Goods Receipts', to: '/purchasing/goods-receipts', icon: PackageCheck },
  { label: 'Supplier Bills', to: '/purchasing/supplier-bills', icon: Receipt },
  { label: 'Accounts Payable', to: '/purchasing/accounts-payable', icon: FileCheck2 },
  { label: 'Payments', to: '/purchasing/payments', icon: Banknote },
  { label: 'Supplier Credits', to: '/purchasing/supplier-credits', icon: FileMinus2 },
  { label: 'Purchase Returns', to: '/purchasing/returns', icon: RotateCcw },
  { label: 'Reports', icon: FileCheck2, unavailable: 'A dedicated purchasing reports workspace is not implemented.' },
  { label: 'Settings', to: '/purchasing/settings', icon: Settings2 },
];

export function PurchasingLayout() {
  const [searchParams, setSearchParams] = useSearchParams();
  const companies = useQuery({ queryKey: ['dashboard-companies', 'purchasing-workspace'], queryFn: getDashboardCompanies });
  const permissions = useQuery({ queryKey: ['me', 'purchasing-workspace'], queryFn: getMe, retry: false });
  const companyList = companies.data?.data.data ?? [];
  const selectedCompanyId = searchParams.get('company_id');
  const companyId = selectedCompanyId ?? companyList[0]?.id ?? '';

  useEffect(() => {
    if (!selectedCompanyId && companyId) {
      const next = new URLSearchParams(searchParams);
      next.set('company_id', companyId);
      setSearchParams(next, { replace: true });
    }
  }, [companyId, searchParams, selectedCompanyId, setSearchParams]);

  const href = (to: string) => `${to}${companyId ? `?company_id=${encodeURIComponent(companyId)}` : ''}`;
  const canManage = permissions.isLoading || (permissions.data?.data.permissions ?? []).includes('procurement.manage') || (permissions.data?.data.permissions ?? []).includes('organization.manage');

  return <div className="purchasing-workspace-layout">
    <header className="purchasing-layout-heading">
      <div><div className="breadcrumb-line"><span>Workspace</span><span>/</span><span>Purchasing</span></div><h1>Purchasing workspace</h1><p className="muted">Manage purchase orders with authorized company and branch context.</p></div>
      {companyId && <span className="purchasing-context-chip"><Truck size={14} />{companyList.find((company) => company.id === companyId)?.name ?? 'Selected company'}</span>}
    </header>
    <nav className="purchasing-nav" aria-label="Purchasing navigation"><div className="purchasing-nav-inner">
      {purchasingNavItems.map(({ label, to, icon: Icon, unavailable }) => to && !unavailable ? <NavLink key={label} to={href(to)} end={to === '/purchasing'} className={({ isActive }) => `purchasing-nav-link ${isActive ? 'active' : ''} ${label === 'Purchase Orders' && !canManage ? 'restricted' : ''}`}><Icon size={14} aria-hidden="true" /><span>{label}</span></NavLink> : <span key={label} className="purchasing-nav-link disabled" aria-disabled="true" title={unavailable}><Icon size={14} aria-hidden="true" /><span>{label}</span><small>Planned</small></span>)}
    </div></nav>
    <Outlet />
  </div>;
}
