import { ArrowLeft } from 'lucide-react';
import { useCallback, useEffect, useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useI18n } from '../lib/i18n';

const HISTORY_KEY = 'erp_cloud.navigation_history';
const BLOCKED_PREFIXES = ['/login', '/register', '/verify-email', '/reset-password', '/access-denied', '/platform/login', '/business/login'];

type HistoryEntry = { pathname: string; search: string };

function isSafe(entry: HistoryEntry, current: HistoryEntry) {
  return entry.pathname !== current.pathname || entry.search !== current.search;
}

function fallbackPath(pathname: string): string {
  if (pathname === '/platform' || pathname === '/platform/dashboard' || pathname.startsWith('/platform/')) return '/platform';
  if (pathname === '/business/dashboard' || pathname === '/app/dashboard' || pathname.startsWith('/business/')) return '/business/dashboard';
  if (pathname === '/') return '/';
  return '';
}

function parentPath(pathname: string): string {
  const fallback = fallbackPath(pathname);
  if (fallback) return fallback;
  if (pathname.startsWith('/organization/')) return '/organization';
  if (pathname.startsWith('/purchasing/')) return '/purchasing';
  if (pathname.startsWith('/crm-sales/')) return '/crm-sales';
  const parts = pathname.split('/').filter(Boolean);
  if (parts.length <= 1) return '/';
  // Detail/edit/create pages fall back to their nearest list/workspace route.
  const parent = `/${parts.slice(0, -1).join('/')}`;
  return parent.endsWith('/edit') || parent.endsWith('/new') ? parentPath(parent) : parent;
}

function isAllowed(pathname: string) {
  return pathname.startsWith('/') && !pathname.startsWith('//') && pathname !== '/' && !BLOCKED_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

function isRootDashboard(pathname: string) {
  return pathname === '/' || pathname === '/platform' || pathname === '/platform/dashboard' || pathname === '/business/dashboard' || pathname === '/app/dashboard';
}

export function GlobalBackButton({ className = '' }: { className?: string }) {
  const { t } = useI18n();
  const location = useLocation();
  const navigate = useNavigate();
  const current = useMemo(() => ({ pathname: location.pathname, search: location.search }), [location.pathname, location.search]);

  useEffect(() => {
    try {
      const stored = JSON.parse(sessionStorage.getItem(HISTORY_KEY) ?? '[]') as HistoryEntry[];
      const previous = stored.at(-1);
      const next = previous && previous.pathname === current.pathname && previous.search === current.search
        ? stored
        : [...stored, current].slice(-40);
      sessionStorage.setItem(HISTORY_KEY, JSON.stringify(next));
    } catch {
      // Navigation must remain usable when storage is unavailable.
    }
  }, [current]);

  const destination = useMemo(() => {
    try {
      const stored = JSON.parse(sessionStorage.getItem(HISTORY_KEY) ?? '[]') as HistoryEntry[];
      const previous = [...stored].reverse().find((entry) => isSafe(entry, current) && isAllowed(entry.pathname));
      if (previous) return `${previous.pathname}${previous.search}`;
    } catch {
      // Use the deterministic route fallback below.
    }
    const parent = parentPath(current.pathname);
    return `${parent}${current.search}`;
  }, [current]);

  const goBack = useCallback(() => {
    const dirtyForm = document.querySelector('form[data-unsaved="true"]');
    if (dirtyForm && !window.confirm(t('org.unsavedChanges', 'You have unsaved changes. Leave this page?'))) return;
    navigate(destination);
  }, [destination, navigate, t]);

  if (isRootDashboard(location.pathname)) return null;

  return <button type="button" className={`erp-back-navigation global-back-button ${className}`.trim()} onClick={goBack} aria-label={t('org.back')}>
    <ArrowLeft size={15} aria-hidden="true" /> <span>{t('org.back')}</span>
  </button>;
}

/** @deprecated Use GlobalBackButton for the application shell. */
export const BackNavigation = GlobalBackButton;

export function PageHeaderWithBack({ children }: { children: ReactNode }) {
  return <div className="erp-page-header-with-back"><BackNavigation />{children}</div>;
}

export function TabBackNavigation() {
  return <BackNavigation className="erp-tab-back-navigation" />;
}
