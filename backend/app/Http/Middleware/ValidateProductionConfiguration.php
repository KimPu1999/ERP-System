<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ValidateProductionConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            $errors = [];
            if (config('app.debug')) $errors[] = 'APP_DEBUG must be false.';
            if (! config('app.key')) $errors[] = 'APP_KEY is required.';
            if (config('mail.default') === 'log') $errors[] = 'MAIL_MAILER=log is not allowed in production.';
            if (! config('session.secure')) $errors[] = 'SESSION_SECURE_COOKIE must be true.';
            if (str_contains((string) config('cors.allowed_origins.0'), 'localhost')) $errors[] = 'FRONTEND_URL must not use localhost.';

            if ($errors !== []) {
                throw new HttpException(503, 'Production configuration is incomplete.', null, ['X-Configuration-Errors' => (string) count($errors)]);
            }
        }

        return $next($request);
    }
}
