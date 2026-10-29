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
        abort_unless($request->user()?->is_platform_super_admin === true, 403, 'Platform administrator access required.');
        $context = app(TenantContext::class);
        $context->setPlatformAdmin(true);
        try { return $next($request); } finally { $context->setPlatformAdmin(false); }
    }
}
