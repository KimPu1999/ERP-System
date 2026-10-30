import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Banknote, Bell, ClipboardCheck, ClipboardList, FileCheck2, FileMinus2, PackageCheck, Receipt, RotateCcw, Settings2, ShieldCheck, ShoppingCart, Truck, Users, type LucideIcon } from 'lucide-react';
import { NavLink, Outlet, useSearchParams } from 'react-router-dom';
import { getDashboardCompanies, getMe } from '../lib/api';
import { useI18n, type TranslationKey } from '../lib/i18n';

type PurchasingNavItem = { label: TranslationKey; to?: string; icon: LucideIcon; unavailable?: string };

const purchasingNavItems: PurchasingNavItem[] = [
  { label: 'purchase.overview', to: '/purchasing', icon: BarChart3 }, { label: 'nav.suppliers', to: '/purchasing/suppliers', icon: Users }, { label: 'purchase.onboarding', to: '/purchasing/suppliers/onboarding', icon: FileCheck2 }, { label: 'purchase.financials', to: '/purchasing/suppliers/financial', icon: Banknote }, { label: 'purchase.catalogs', to: '/purchasing/suppliers/catalog', icon: ClipboardList }, { label: 'purchase.performance', to: '/purchasing/suppliers/performance', icon: BarChart3 }, { label: 'purchase.intelligence', to: '/purchasing/suppliers/analytics', icon: BarChart3 }, { label: 'purchase.reports', to: '/purchasing/suppliers/reports', icon: FileCheck2 }, { label: 'purchase.automations', to: '/purchasing/suppliers/automations', icon: Settings2 }, { label: 'purchase.notifications', to: '/purchasing/suppliers/notifications', icon: Bell }, { label: 'purchase.portalActivity', to: '/purchasing/suppliers/portal-activity', icon: Users }, { label: 'purchase.accessReviews', to: '/purchasing/suppliers/access-reviews', icon: ShieldCheck }, { label: 'purchase.requests', icon: ClipboardList, unavailable: 'Purchase request management UI is planned for the next purchasing phase.' }, { label: 'purchase.orders', to: '/purchasing', icon: ShoppingCart }, { label: 'purchase.approvals', to: '/purchasing/approvals', icon: ClipboardCheck }, { label: 'purchase.receipts', to: '/purchasing/goods-receipts', icon: PackageCheck }, { label: 'purchase.bills', to: '/purchasing/supplier-bills', icon: Receipt }, { label: 'purchase.payables', to: '/purchasing/accounts-payable', icon: FileCheck2 }, { label: 'purchase.payments', to: '/purchasing/payments', icon: Banknote }, { label: 'purchase.credits', to: '/purchasing/supplier-credits', icon: FileMinus2 }, { label: 'purchase.returns', to: '/purchasing/returns', icon: RotateCcw }, { label: 'purchase.reports', icon: FileCheck2, unavailable: 'A dedicated purchasing reports workspace is not implemented.' }, { label: 'purchase.settings', to: '/purchasing/settings', icon: Settings2 },
];

export function PurchasingLayout() {
  const { t } = useI18n();
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
      <span className="workspace-shell-label">{t('nav.purchasing')}</span>
      {companyId && <span className="purchasing-context-chip"><Truck size={14} />{companyList.find((company) => company.id === companyId)?.name ?? t('org.selectedCompany')}</span>}
    </header>
    <nav className="purchasing-nav" aria-label={t('nav.purchasing')}><div className="purchasing-nav-inner">
      {purchasingNavItems.map(({ label, to, icon: Icon, unavailable }) => { const text = t(label); return to && !unavailable ? <NavLink key={`${label}-${to}`} to={href(to)} end={to === '/purchasing'} className={({ isActive }) => `purchasing-nav-link ${isActive ? 'active' : ''} ${label === 'purchase.orders' && !canManage ? 'restricted' : ''}`}><Icon size={14} aria-hidden="true" /><span>{text}</span></NavLink> : <span key={`${label}-${to ?? 'disabled'}`} className="purchasing-nav-link disabled" aria-disabled="true" title={unavailable}><Icon size={14} aria-hidden="true" /><span>{text}</span><small>Planned</small></span>; })}
    </div></nav>
    <Outlet />
  </div>;
}
