<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MonitoringService;
use Illuminate\Http\JsonResponse;

final class PlatformHealthController
{
    public function __invoke(MonitoringService $monitoring): JsonResponse
    {
        return response()->json(['data' => $monitoring->legacyHealth()]);
    }
}
