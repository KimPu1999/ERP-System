<?php

namespace App\Http\Middleware;

use Closure;
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
        try {
            $context->set($tenantId);
            abort_unless($user->tenantMemberships()->where('tenant_id', $tenantId)->where('status', 'active')->exists(), 403, 'Tenant access denied.');
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
