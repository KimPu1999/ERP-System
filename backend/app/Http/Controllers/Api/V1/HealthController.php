<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => false, 'cache' => false];
        try { DB::connection()->getPdo(); $checks['database'] = true; } catch (Throwable) {}
        try { Cache::store()->put('health-check', true, 5); $checks['cache'] = Cache::store()->get('health-check') === true; } catch (Throwable) {}
        $healthy = ! in_array(false, $checks, true);
        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'service' => 'enterprise-erp-api', 'checks' => $checks], $healthy ? 200 : 503);
    }
}
