<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

final class TenantMemberController
{
    public function index(): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        return response()->json(['data' => User::query()->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))->orderBy('name')->paginate(25)]);
    }
}
