<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RoleController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($authorization->allows($request->user(), $tenantId, 'roles.manage'), 403);
        $query = Role::query()->where(fn ($role) => $role->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->with('permissions:id,name')->withCount('assignments');
        if ($request->filled('search')) $query->where('name', 'ilike', '%'.$request->string('search')->toString().'%');
        if ($request->query('type') === 'system') $query->whereNull('tenant_id');
        if ($request->query('type') === 'custom') $query->where('tenant_id', $tenantId);
        $perPage = min(max($request->integer('per_page', 25), 10), 100);
        $roles = $query->orderBy('name')->paginate($perPage)->withQueryString();
        return response()->json(['data' => $roles->getCollection()->values(), 'meta' => ['current_page' => $roles->currentPage(), 'last_page' => $roles->lastPage(), 'per_page' => $roles->perPage(), 'total' => $roles->total()], 'summary' => ['total_roles' => (clone $query)->count(), 'custom_roles' => (clone $query)->where('tenant_id', $tenantId)->count(), 'active_assignments' => RoleAssignment::where('tenant_id', $tenantId)->count(), 'pending_access_reviews' => ['available' => false, 'reason' => 'Access review workflow is not configured.']]]);
    }

    public function permissions(Request $request, AuthorizationService $authorization): JsonResponse
    {
        abort_unless($authorization->allows($request->user(), (string) app(TenantContext::class)->id(), 'roles.manage'), 403);
        return response()->json(['data' => Permission::query()->orderBy('name')->get(['id', 'name', 'description'])]);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertManage($request, $authorization, $tenantId);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('roles', 'slug')->where(fn ($query) => $query->where('tenant_id', $tenantId))], 'permission_ids' => ['array'], 'permission_ids.*' => ['uuid', 'exists:permissions,id']]);
        $this->assertPermissionsAssignable($request, $authorization, $tenantId, $data['permission_ids'] ?? []);
        $role = DB::transaction(function () use ($data, $tenantId): Role { $role = Role::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => $data['slug']]); $role->permissions()->sync($data['permission_ids'] ?? []); return $role->load('permissions'); });
        $audit->record($request, 'role.created', $tenantId, $role);
        return response()->json(['data' => $role], 201);
    }

    public function update(Request $request, Role $role, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($role->tenant_id === null || $role->tenant_id === $tenantId, 404);
        $this->assertManage($request, $authorization, $tenantId);
        abort_if($role->tenant_id === null, 403, 'System-owned roles are read-only.');
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'permission_ids' => ['sometimes', 'array'], 'permission_ids.*' => ['uuid', 'exists:permissions,id']]);
        if (array_key_exists('permission_ids', $data)) $this->assertPermissionsAssignable($request, $authorization, $tenantId, $data['permission_ids']);
        $role->update(collect($data)->except('permission_ids')->all());
        if (array_key_exists('permission_ids', $data)) $role->permissions()->sync($data['permission_ids']);
        $audit->record($request, 'role.updated', $tenantId, $role);
        return response()->json(['data' => $role->fresh('permissions')]);
    }

    private function assertManage(Request $request, AuthorizationService $authorization, string $tenantId): void { abort_unless($authorization->allows($request->user(), $tenantId, 'roles.manage'), 403); }
    private function assertPermissionsAssignable(Request $request, AuthorizationService $authorization, string $tenantId, array $permissionIds): void
    {
        $actorPermissions = RoleAssignment::where('tenant_id', $tenantId)->where('user_id', $request->user()->id)->with('role.permissions')->get()->flatMap(fn (RoleAssignment $assignment) => $assignment->role?->permissions?->pluck('name') ?? [])->unique();
        $requested = Permission::whereIn('id', $permissionIds)->pluck('name');
        if ($requested->diff($actorPermissions)->isNotEmpty()) throw ValidationException::withMessages(['permission_ids' => ['You cannot grant permissions that you do not hold.']]);
    }
}
