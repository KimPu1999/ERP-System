<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-Id');
        $user = $request->user();
        abort_unless($user && $tenantId && preg_match('/^[0-9a-f-]{36}$/i', $tenantId), 403, 'Tenant context is invalid.');
        $context = app(TenantContext::class);
        $previous = $context->snapshot();
        $requestError = null;
        try {
            $context->set($tenantId);
            abort_unless($user->tenantMemberships()->where('tenant_id', $tenantId)->where('status', 'active')->exists(), 403, 'Tenant access denied.');
            abort_unless(Tenant::whereKey($tenantId)->where('status', 'active')->exists(), 403, 'Workspace is inactive.');
            return $next($request);
        } catch (\Throwable $caught) {
            $requestError = $caught;
            throw $caught;
        } finally {
            try {
                // Restore the caller's exact security context: unconditional
                // clearing would silently rewrite platform/tenant state on
                // connections shared with platform sweeps or nested requests.
                $context->restoreSnapshot($previous);
            } catch (\Throwable $restoreError) {
                report($restoreError);
                if ($requestError !== null) {
                    // The request already failed (e.g. an aborted transaction);
                    // surface the original root cause instead of the restore error.
                    throw $requestError;
                }
                throw $restoreError;
            }
        }
    }
}
