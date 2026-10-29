<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\RoleAssignment;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $permissions = $tenantId ? RoleAssignment::query()->where('tenant_id', $tenantId)->where('user_id', $request->user()->id)->with('role.permissions:id,name')->get()->flatMap(fn (RoleAssignment $assignment) => $assignment->role?->permissions?->pluck('name') ?? [])->unique()->values()->all() : [];
        return response()->json(['data' => [...$request->user()->only(['id', 'name', 'email']), 'permissions' => $permissions]]);
    }
}
