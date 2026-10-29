import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../lib/auth';

export function AuthenticatedRoute() {
  const { user, isLoading } = useAuth();
  const location = useLocation();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  return <Outlet />;
}

export function PlatformRoute() {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (!user) return <Navigate to="/login" replace />;
  if (!user.is_platform_super_admin) return <Navigate to="/" replace />;
  return <Outlet />;
}
