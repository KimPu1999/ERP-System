import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getPlatformSetting } from '../lib/api';
import { useAuth } from '../lib/auth';

const cssVariables: Record<string, string> = {
  primary: '--blue',
  primary_hover: '--blue-hover',
  page_background: '--background',
  card_background: '--surface',
  header_background: '--header-background',
  sidebar_background: '--sidebar-background',
  text: '--text',
  muted_text: '--muted',
  border: '--border',
  active_background: '--blue-soft',
  hover_background: '--surface-hover',
};

export const premiumEmeraldAppearance = {
  primary: '#087F5B', primary_hover: '#066B4D', page_background: '#F7F8F5', card_background: '#FFFFFF',
  header_background: '#FFFFFF', sidebar_background: '#FFFFFF', text: '#1C2924', muted_text: '#61716A',
  border: '#E2E7E3', active_background: '#E3F3EB', hover_background: '#EDF5F1',
};

function applyAppearance(value: Record<string, unknown> | undefined) {
  const root = document.documentElement;
  Object.entries(cssVariables).forEach(([key, variable]) => {
    const color = typeof value?.[key] === 'string' ? String(value[key]) : premiumEmeraldAppearance[key as keyof typeof premiumEmeraldAppearance];
    if (root.classList.contains('dark')) root.style.removeProperty(variable);
    else root.style.setProperty(variable, color);
  });
}

export function AppearanceThemeSync() {
  const { user } = useAuth();
  const query = useQuery({ queryKey: ['platform-setting', 'appearance'], queryFn: () => getPlatformSetting<Record<string, unknown>>('appearance'), enabled: Boolean(user?.is_platform_super_admin), staleTime: 300_000, retry: false });
  useEffect(() => {
    const sync = () => applyAppearance(query.data?.data.value);
    sync();
    const observer = new MutationObserver(sync);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    return () => observer.disconnect();
  }, [query.data]);
  return null;
}
