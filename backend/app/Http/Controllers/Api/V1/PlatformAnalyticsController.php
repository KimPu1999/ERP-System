<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PlatformOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformAnalyticsController
{
    public function __invoke(Request $request, PlatformOverviewService $overview): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly,yearly,custom'],
            'granularity' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
        ]);
        return response()->json(['data' => $overview->analytics($request)]);
    }
}
