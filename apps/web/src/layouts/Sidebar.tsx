import { NavLink } from 'react-router-dom';
import { Boxes, Building2, ChevronLeft, ClipboardCheck, FileBarChart2, LayoutDashboard, Package, PanelLeftClose, Settings, ShoppingCart, Users, WalletCards, X } from 'lucide-react';
import { useState } from 'react';
import { Brand } from '../components/Brand';

type Item = { label: string; to: string; icon: typeof LayoutDashboard };
const companyGroups: Array<{ label: string; items: Item[] }> = [
  { label: 'Workspace', items: [{ label: 'Dashboard', to: '/', icon: LayoutDashboard }, { label: 'Organization', to: '/organization', icon: Building2 }, { label: 'Company & branches', to: '/company', icon: Building2 }] },
  { label: 'CRM & Sales', items: [{ label: 'Sales workspace', to: '/crm-sales', icon: Users }] },
  { label: 'Purchasing', items: [{ label: 'Purchase orders', to: '/purchasing', icon: ShoppingCart }, { label: 'Suppliers', to: '/purchasing/suppliers', icon: Users }] },
  { label: 'Products', items: [{ label: 'Product catalog', to: '/catalog', icon: Package }] },
  { label: 'Inventory', items: [{ label: 'Inventory control', to: '/inventory', icon: Boxes }] },
  { label: 'Accounting', items: [{ label: 'Accounting', to: '/accounting', icon: WalletCards }] },
  { label: 'HR & Payroll', items: [{ label: 'HR & payroll', to: '/hr', icon: Users }] },
  { label: 'POS · Projects · Assets', items: [{ label: 'Optional modules', to: '/optional-modules', icon: Building2 }] },
  { label: 'Approvals & Reports', items: [{ label: 'Approvals', to: '/workflow', icon: ClipboardCheck }, { label: 'Reports', to: '/workflow', icon: FileBarChart2 }] },
  { label: 'Settings', items: [{ label: 'Access & settings', to: '/organization/roles', icon: Settings }] },
];
const platformItems: Item[] = [{ label: 'Overview', to: '/platform', icon: LayoutDashboard }, { label: 'Tenant Management', to: '/platform/tenants', icon: Building2 }, { label: 'Monitoring · System Health', to: '/platform/monitoring', icon: ClipboardCheck }, { label: 'Subscriptions & Billing', to: '/platform/control', icon: WalletCards }];

export function Sidebar({ platform = false, collapsed, mobileOpen, onCollapse, onClose }: { platform?: boolean; collapsed: boolean; mobileOpen: boolean; onCollapse: () => void; onClose: () => void }) {
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({ Workspace: true, 'CRM & Sales': true, Purchasing: true, Products: true, Inventory: true, Accounting: true, 'HR & Payroll': true, 'POS · Projects · Assets': true, 'Approvals & Reports': true, Settings: false });
  const groups = platform ? [{ label: 'Platform', items: platformItems }] : companyGroups;
  return <aside className={`sidebar ${collapsed ? 'sidebar-collapsed' : ''} ${mobileOpen ? 'sidebar-mobile-open' : ''}`}>
    <div className="sidebar-top"><Brand compact={collapsed} /><button className="icon-button sidebar-close" aria-label="Close navigation" onClick={onClose}><X size={18} /></button><button className="icon-button sidebar-collapse" aria-label={collapsed ? 'Expand navigation' : 'Collapse navigation'} onClick={onCollapse}>{collapsed ? <ChevronLeft size={18} /> : <PanelLeftClose size={18} />}</button></div>
    <div className="workspace-badge"><span className="workspace-dot" />{!collapsed && <span>{platform ? 'Platform operations' : 'Acme workspace'}</span>}</div>
    <nav className="side-nav" aria-label={platform ? 'Platform navigation' : 'Company navigation'}>{groups.map((group) => <div className="nav-group" key={group.label}>{!collapsed && <button className="nav-group-toggle" onClick={() => setOpenGroups((current) => ({ ...current, [group.label]: !current[group.label] }))} aria-expanded={openGroups[group.label]}><span className="nav-group-label">{group.label}</span><span>{openGroups[group.label] ? '−' : '+'}</span></button>}{(collapsed || openGroups[group.label]) && group.items.map(({ label, to, icon: Icon }) => <NavLink end={to === '/'} key={`${group.label}-${to}-${label}`} to={to} onClick={onClose} title={collapsed ? label : undefined} className={({ isActive }) => `side-link ${isActive ? 'active' : ''}`}><Icon size={18} aria-hidden="true" /><span>{!collapsed && label}</span></NavLink>)}</div>)}</nav>
    <div className="sidebar-bottom"><div className="sidebar-version">{!collapsed && 'ERP Cloud · v1.0'}</div></div>
  </aside>;
}
