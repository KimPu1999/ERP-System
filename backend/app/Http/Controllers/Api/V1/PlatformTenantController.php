<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformTenantController
{
    public function index(): JsonResponse { return response()->json(['data' => Tenant::query()->withCount('users')->orderBy('name')->paginate(25)]); }
    public function store(Request $request, AuditLogger $audit): JsonResponse { $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'slug' => ['required', 'alpha_dash', 'max:80', 'unique:tenants,slug']]); $tenant = Tenant::create($data); $audit->record($request, 'platform.tenant.created', null, $tenant); return response()->json(['data' => $tenant], 201); }
}
