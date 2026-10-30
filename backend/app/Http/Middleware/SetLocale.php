<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = (string) ($request->header('X-Locale') ?: $request->getPreferredLanguage(['en', 'my-MM']));
        app()->setLocale(strtolower($requested) === 'my-mm' || strtolower($requested) === 'my' ? 'my' : 'en');
        return $next($request);
    }
}
