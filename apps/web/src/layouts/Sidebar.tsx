import { NavLink } from 'react-router-dom';
import { Boxes, Building2, ChevronLeft, ClipboardCheck, FileBarChart2, LayoutDashboard, Link2, Package, PanelLeftClose, Settings, ShoppingCart, Users, WalletCards, Warehouse, X } from 'lucide-react';
import { useState } from 'react';
import { Brand } from '../components/Brand';
import { useI18n, type TranslationKey } from '../lib/i18n';

type Item = { key: TranslationKey; to: string; icon: typeof LayoutDashboard };
type Group = { key: TranslationKey; items: Item[] };
const companyGroups: Group[] = [
  { key: 'nav.workspace', items: [{ key: 'nav.dashboard', to: '/', icon: LayoutDashboard }, { key: 'nav.organization', to: '/organization', icon: Building2 }, { key: 'nav.companyBranches', to: '/company', icon: Building2 }] },
  { key: 'nav.crmSales', items: [{ key: 'nav.salesWorkspace', to: '/crm-sales', icon: Users }] },
  { key: 'nav.purchasing', items: [{ key: 'nav.purchaseOrders', to: '/purchasing', icon: ShoppingCart }, { key: 'nav.suppliers', to: '/purchasing/suppliers', icon: Users }] },
  { key: 'nav.products', items: [{ key: 'nav.productCatalog', to: '/catalog', icon: Package }, { key: 'nav.productCategories', to: '/catalog/categories', icon: Package }, { key: 'nav.catalogReferences', to: '/catalog/references', icon: Link2 }] },
  { key: 'nav.inventory', items: [{ key: 'nav.inventoryControl', to: '/inventory', icon: Boxes }, { key: 'nav.inventoryControl', to: '/inventory/warehouses', icon: Warehouse }, { key: 'nav.inventoryControl', to: '/inventory/operations', icon: ClipboardCheck }] },
  { key: 'nav.accounting', items: [{ key: 'nav.accounting', to: '/accounting', icon: WalletCards }, { key: 'nav.journals', to: '/accounting/journals', icon: FileBarChart2 }, { key: 'nav.financialReports', to: '/accounting/reports', icon: FileBarChart2 }, { key: 'nav.accountingIntegration', to: '/accounting/integration', icon: Link2 }] },
  { key: 'nav.hrPayroll', items: [{ key: 'nav.hr', to: '/hr', icon: Users }] },
  { key: 'nav.optional', items: [{ key: 'nav.optionalModules', to: '/optional-modules', icon: Building2 }] },
  { key: 'nav.approvalsReports', items: [{ key: 'nav.approvals', to: '/workflow', icon: ClipboardCheck }, { key: 'nav.reports', to: '/workflow', icon: FileBarChart2 }] },
  { key: 'nav.settings', items: [{ key: 'nav.accessSettings', to: '/organization/roles', icon: Settings }] },
];
const platformItems: Item[] = [{ key: 'nav.overview', to: '/platform', icon: LayoutDashboard }, { key: 'nav.tenantManagement', to: '/platform/tenants', icon: Building2 }, { key: 'nav.monitoring', to: '/platform/monitoring', icon: ClipboardCheck }, { key: 'nav.subscriptions', to: '/platform/control', icon: WalletCards }];

export function Sidebar({ platform = false, collapsed, mobileOpen, onCollapse, onClose }: { platform?: boolean; collapsed: boolean; mobileOpen: boolean; onCollapse: () => void; onClose: () => void }) {
  const { t } = useI18n();
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({ 'nav.workspace': true, 'nav.crmSales': true, 'nav.purchasing': true, 'nav.products': true, 'nav.inventory': true, 'nav.accounting': true, 'nav.hrPayroll': true, 'nav.optional': true, 'nav.approvalsReports': true, 'nav.settings': false, 'nav.platform': true });
  const groups = platform ? [{ key: 'nav.platform' as TranslationKey, items: platformItems }] : companyGroups;
  return <aside className={`sidebar ${collapsed ? 'sidebar-collapsed' : ''} ${mobileOpen ? 'sidebar-mobile-open' : ''}`}>
    <div className="sidebar-top"><Brand compact={collapsed} /><button className="icon-button sidebar-close" aria-label={t('app.closeNavigation')} onClick={onClose}><X size={18} /></button><button className="icon-button sidebar-collapse" aria-label={collapsed ? t('app.expandNavigation') : t('app.collapseNavigation')} onClick={onCollapse}>{collapsed ? <ChevronLeft size={18} /> : <PanelLeftClose size={18} />}</button></div>
    <div className="workspace-badge"><span className="workspace-dot" />{!collapsed && <span>{platform ? t('app.platform') : t('app.workspace')}</span>}</div>
    <nav className="side-nav" aria-label={platform ? t('app.platform') : t('nav.workspace')}>{groups.map((group) => { const groupLabel = t(group.key); return <div className="nav-group" key={group.key}>{!collapsed && <button className="nav-group-toggle" onClick={() => setOpenGroups((current) => ({ ...current, [group.key]: !current[group.key] }))} aria-expanded={openGroups[group.key]}><span className="nav-group-label">{groupLabel}</span><span>{openGroups[group.key] ? '−' : '+'}</span></button>}{(collapsed || openGroups[group.key]) && group.items.map(({ key, to, icon: Icon }) => { const label = t(key); return <NavLink end={to === '/'} key={`${group.key}-${to}-${key}`} to={to} onClick={onClose} title={collapsed ? label : undefined} className={({ isActive }) => `side-link ${isActive ? 'active' : ''}`}><Icon size={18} aria-hidden="true" /><span>{!collapsed && label}</span></NavLink>; })}</div>; })}</nav>
    <div className="sidebar-bottom"><div className="sidebar-version">{!collapsed && 'ERP Cloud · v1.0'}</div></div>
  </aside>;
}
