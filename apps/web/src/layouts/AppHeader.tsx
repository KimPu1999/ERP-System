import { Bell, ChevronDown, Globe2, LogOut, Menu, Moon, Search, Sun } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { logout } from '../lib/api';
import { useAuth } from '../lib/auth';

export function AppHeader({ onOpenSidebar, platform = false }: { onOpenSidebar: () => void; platform?: boolean }) {
  const { user, refresh } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));
  const navigate = useNavigate();
  const toggleTheme = () => { document.documentElement.classList.toggle('dark', !dark); setDark(!dark); };
  const signOut = async () => { await logout(); await refresh(); navigate('/login', { replace: true }); };
  return <header className="app-header"><button className="icon-button mobile-menu" aria-label="Open navigation" onClick={onOpenSidebar}><Menu size={20} /></button><div className="header-context"><span className="header-kicker">{platform ? 'Platform control' : 'Company workspace'}</span><span className="header-title">{platform ? 'Platform operations' : 'Acme Industries'}</span></div><div className="header-actions"><label className="header-search"><Search size={17} /><input aria-label="Search" placeholder="Search anything…" /></label><button className="header-action" aria-label="Change language"><Globe2 size={18} /><span>EN</span></button><button className="header-action theme-toggle" aria-label="Toggle dark mode" onClick={toggleTheme}>{dark ? <Sun size={18} /> : <Moon size={18} />}</button><button className="header-action notification" aria-label="Notifications"><Bell size={18} /><i /></button><div className="profile-wrap"><button className="profile-trigger" onClick={() => setMenuOpen(!menuOpen)} aria-expanded={menuOpen}><span className="avatar">{user?.name?.slice(0, 1).toUpperCase() ?? 'U'}</span><span className="profile-name">{user?.name ?? 'Account'}</span><ChevronDown size={15} /></button>{menuOpen && <div className="profile-menu"><div className="profile-menu-user"><strong>{user?.name}</strong><span>{user?.email}</span></div><button onClick={signOut}><LogOut size={16} /> Sign out</button></div>}</div></div></header>;
}
