import { createContext, useContext, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ApiError, api, type User } from './api';

type AuthValue = { user: User | null; isLoading: boolean; refresh: () => Promise<void> };
const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const query = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      try { return (await api<{ data: User }>('/auth/me')).data; }
      catch (error) { if (error instanceof ApiError && error.status === 401) return null; throw error; }
    },
    retry: false,
    staleTime: 60_000,
  });
  return <AuthContext.Provider value={{ user: query.data ?? null, isLoading: query.isPending, refresh: async () => { await query.refetch(); } }}>{children}</AuthContext.Provider>;
}

// The hook is intentionally colocated with its provider so route guards share one session source.
// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthValue {
  const value = useContext(AuthContext);
  if (!value) throw new Error('useAuth must be used inside AuthProvider');
  return value;
}
