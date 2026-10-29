<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantContextController
{
    public function index(Request $request): JsonResponse { return response()->json(['data' => $request->user()->tenants()->wherePivot('status', 'active')->orderBy('name')->get(['tenants.id', 'tenants.name', 'tenants.slug'])]); }
}
