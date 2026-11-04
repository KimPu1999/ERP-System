<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlatformSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
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
        $context = app(TenantContext::class);
        $previous = $context->snapshot();
        $context->setPlatformAdmin(true);
        try { return $next($request); } finally { $context->restoreSnapshot($previous); }
    }
}
