import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../lib/auth';
import { ApiError } from '../lib/api';

function isSuspended(error: unknown, user: ReturnType<typeof useAuth>['user']) {
  return (error instanceof ApiError && error.status === 423) || Boolean(user && (user.status !== 'active' || user.suspended_at));
}

export function AuthenticatedRoute() {
  const { user, isLoading, error } = useAuth();
  const location = useLocation();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (isSuspended(error, user)) return <Navigate to="/access-denied?reason=suspended" replace />;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace state={{ from: location.pathname }} />;
  if (user.is_platform_super_admin) return <Navigate to="/platform" replace />;
  return <Outlet />;
}

export function PlatformRoute() {
  const { user, isLoading, error } = useAuth();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (isSuspended(error, user)) return <Navigate to="/access-denied?reason=suspended" replace />;
  if (!user) return <Navigate to="/platform/login" replace />;
  if (!user.email_verified_at || user.status !== 'active') return <Navigate to="/platform/login" replace />;
  if (!user.is_platform_super_admin) return <Navigate to="/access-denied?reason=platform" replace />;
  return <Outlet />;
}

export function BusinessRoute() {
  const { user, isLoading, error } = useAuth();
  if (isLoading) return <div className="route-loading" role="status">Checking your session…</div>;
  if (isSuspended(error, user)) return <Navigate to="/access-denied?reason=suspended" replace />;
  if (!user) return <Navigate to="/business/login" replace />;
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace state={{ from: '/business/dashboard' }} />;
  if (user.is_platform_super_admin) return <Navigate to="/platform" replace />;
  return <Outlet />;
}
