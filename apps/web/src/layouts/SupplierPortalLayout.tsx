import { Bell, FileText, LayoutDashboard } from 'lucide-react';
import { NavLink, Outlet } from 'react-router-dom';

const links = [
  ['/supplier-portal/dashboard', 'Dashboard', LayoutDashboard],
  ['/supplier-portal/purchase-orders', 'Purchase Orders', FileText],
];

export function SupplierPortalLayout() {
  return <div className="supplier-portal-shell"><header className="supplier-portal-header"><div><span className="eyebrow">ERP Cloud</span><strong>Supplier Portal</strong></div><Bell size={18} aria-label="Notifications" /></header><div className="supplier-portal-body"><aside className="supplier-portal-sidebar" aria-label="Supplier portal navigation">{links.map(([to, label, Icon]) => <NavLink key={to as string} to={to as string} className={({ isActive }) => isActive ? 'active' : ''}><Icon size={16} /><span>{label as string}</span></NavLink>)}</aside><main className="supplier-portal-content"><Outlet /></main></div></div>;
}
