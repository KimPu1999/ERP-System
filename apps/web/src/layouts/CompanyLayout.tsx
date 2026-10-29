import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { AppHeader } from './AppHeader';
import { Sidebar } from './Sidebar';

export function CompanyLayout({ platform = false }: { platform?: boolean }) { const [collapsed, setCollapsed] = useState(false); const [mobileOpen, setMobileOpen] = useState(false); return <div className="app-shell"><Sidebar platform={platform} collapsed={collapsed} mobileOpen={mobileOpen} onCollapse={() => setCollapsed(!collapsed)} onClose={() => setMobileOpen(false)} /><div className="app-viewport"><AppHeader platform={platform} onOpenSidebar={() => setMobileOpen(true)} /><main className="content"><Outlet /></main></div>{mobileOpen && <button className="sidebar-overlay" aria-label="Close navigation overlay" onClick={() => setMobileOpen(false)} />}</div>; }
