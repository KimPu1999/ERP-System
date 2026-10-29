<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuthorizationService;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RoleController
{
    public function index(): JsonResponse { return response()->json(['data' => Role::query()->where('tenant_id', app(TenantContext::class)->id())->with('permissions:id,name')->orderBy('name')->get()]); }
    public function permissions(): JsonResponse { return response()->json(['data' => Permission::query()->orderBy('name')->get(['id', 'name', 'description'])]); }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'roles.manage'), 403); $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100'], 'permission_ids' => ['array'], 'permission_ids.*' => ['uuid', 'exists:permissions,id']]); $role = DB::transaction(function () use ($data, $tenantId): Role { $role = Role::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => $data['slug']]); $role->permissions()->sync($data['permission_ids'] ?? []); return $role->load('permissions'); }); $audit->record($request, 'role.created', $tenantId, $role); return response()->json(['data' => $role], 201); }
    public function update(Request $request, Role $role, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { abort_unless($role->tenant_id === app(TenantContext::class)->id(), 404); abort_unless($authorization->allows($request->user(), $role->tenant_id, 'roles.manage'), 403); $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'permission_ids' => ['sometimes', 'array'], 'permission_ids.*' => ['uuid', 'exists:permissions,id']]); $role->update($data); if (array_key_exists('permission_ids', $data)) $role->permissions()->sync($data['permission_ids']); $audit->record($request, 'role.updated', $role->tenant_id, $role); return response()->json(['data' => $role->fresh('permissions')]); }
}
