<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PlatformHealthController
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();
        $cache = $this->checkCache();
        $queue = config('queue.default') === 'sync'
            ? ['status' => 'not_configured', 'detail' => 'The synchronous queue driver is configured.']
            : ['status' => 'unknown', 'detail' => 'A worker heartbeat is not configured.'];
        $mail = in_array(config('mail.default'), ['log', 'array'], true)
            ? ['status' => 'not_configured', 'detail' => 'No external mail transport is configured.']
            : ['status' => 'configured', 'detail' => 'The mail transport is configured; delivery is not tested here.'];
        $scheduler = ['status' => 'unknown', 'detail' => 'A scheduler heartbeat is not configured.'];
        $statuses = [$database['status'], $cache['status'], $queue['status'], $mail['status'], $scheduler['status']];
        $overall = in_array('unavailable', $statuses, true) ? 'degraded' : (in_array('unknown', $statuses, true) || in_array('not_configured', $statuses, true) ? 'unknown' : 'healthy');

        return response()->json(['data' => [
            'status' => $overall,
            'checked_at' => now('UTC')->toISOString(),
            'checks' => compact('database', 'cache', 'queue', 'mail', 'scheduler'),
        ]]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy'];
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'platform-health-'.bin2hex(random_bytes(8));
            Cache::store()->put($key, true, 5);
            $healthy = Cache::store()->get($key) === true;
            Cache::store()->forget($key);
            return ['status' => $healthy ? 'healthy' : 'unavailable'];
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }
    }
}
