import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../lib/auth';

export function AuthenticatedRoute() {
  const { user, isLoading } = useAuth();
  const location = useLocation();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace state={{ from: location.pathname }} />;
  if (user.is_platform_super_admin) return <Navigate to="/platform" replace />;
  return <Outlet />;
}

export function PlatformRoute() {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (!user) return <Navigate to="/platform/login" replace />;
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace state={{ from: '/platform' }} />;
  if (!user.is_platform_super_admin) return <Navigate to="/" replace />;
  return <Outlet />;
}

export function BusinessRoute() {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (!user) return <Navigate to="/business/login" replace />;
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace state={{ from: '/business/dashboard' }} />;
  if (user.is_platform_super_admin) return <Navigate to="/platform" replace />;
  return <Outlet />;
}
