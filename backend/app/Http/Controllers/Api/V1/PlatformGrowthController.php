<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PlatformGrowthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformGrowthController
{
    public function overview(Request $request, PlatformGrowthService $growth): JsonResponse
    {
        $this->validateFilters($request);
        return response()->json(['data' => $growth->overview($request)]);
    }

    public function onboarding(Request $request, PlatformGrowthService $growth): JsonResponse
    {
        $this->validateFilters($request, cohort: true);
        $this->validatePagination($request);
        return response()->json(['data' => $growth->onboarding($request)]);
    }

    public function adoption(Request $request, PlatformGrowthService $growth): JsonResponse
    {
        $this->validateFilters($request);
        return response()->json(['data' => $growth->adoption($request)]);
    }

    public function customerSuccess(Request $request, PlatformGrowthService $growth): JsonResponse
    {
        $this->validateFilters($request);
        $this->validatePagination($request);
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'sort' => ['nullable', 'in:name,created_at,status'], 'direction' => ['nullable', 'in:asc,desc']]);
        return response()->json(['data' => $growth->customerSuccess($request)]);
    }

    public function retention(Request $request, PlatformGrowthService $growth): JsonResponse
    {
        $this->validateFilters($request);
        return response()->json(['data' => $growth->retention($request)]);
    }

    private function validateFilters(Request $request, bool $cohort = false): void
    {
        $request->validate([
            'period' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,this_month,last_month,this_year,daily,weekly,monthly,yearly,custom'],
            'granularity' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'from' => [$cohort ? 'required_if:period,custom' : 'nullable', 'nullable', 'date_format:Y-m-d'],
            'to' => [$cohort ? 'required_if:period,custom' : 'nullable', 'nullable', 'date_format:Y-m-d'],
        ]);
        if ($request->filled('plan_id')) $request->validate(['plan_id' => ['uuid']]);
        if ($request->filled('business_type')) $request->validate(['business_type' => ['string', 'max:60']]);
        if ($request->filled('status')) $request->validate(['status' => ['in:pending,active,suspended,rejected']]);
    }

    private function validatePagination(Request $request): void
    {
        if ($request->filled('per_page')) $request->validate(['per_page' => ['integer', 'min:10', 'max:100']]);
    }
}
