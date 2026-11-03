<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMfaCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        // Session stores expose get/has, but not Request::boolean-style helpers.
        // Cast the stored value explicitly so both database and cookie sessions
        // behave consistently across Laravel versions.
        if (! (bool) $request->session()->get('auth.login_pending', false)) {
            return $next($request);
        }

        $allowed = $request->is('api/v1/auth/me')
            || $request->is('api/v1/auth/logout')
            || $request->is('api/v1/auth/mfa/enroll')
            || $request->is('api/v1/auth/mfa/confirm');

        abort_unless($allowed, 403, 'MFA verification is required before accessing this resource.');

        return $next($request);
    }
}
