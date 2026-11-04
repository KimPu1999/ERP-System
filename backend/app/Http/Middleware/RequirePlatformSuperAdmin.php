<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\SecurityPolicy;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlatformSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $policy = app(SecurityPolicy::class);
        $user = $request->user();
        abort_unless(
            $user
            && $user->is_platform_super_admin === true
            && $user->status === 'active'
            && ! $user->suspended_at
            && $user->hasVerifiedEmail(),
            403,
            'Active verified platform administrator access required.',
        );
        abort_unless(! $policy->platformMfaRequired() || $user->mfa_enabled_at, 403, 'Platform MFA enrollment is required before platform access can be granted.');
        $context = app(TenantContext::class);
        $context->setPlatformAdmin(true);
        try { return $next($request); } finally { $context->setPlatformAdmin(false); }
    }
}
