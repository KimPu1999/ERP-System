<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRecentStepUp
{
    public function handle(Request $request, Closure $next): Response
    {
        $until = $request->session()->get('security.step_up_until');
        abort_unless(is_numeric($until) && (int) $until > now()->timestamp, 428, 'Recent authentication is required for this action.');
        return $next($request);
    }
}
