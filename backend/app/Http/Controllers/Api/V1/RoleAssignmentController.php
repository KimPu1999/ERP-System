<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RoleAssignmentController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertManage($request, $authorization, $tenantId);
        $query = RoleAssignment::where('tenant_id', $tenantId)->with(['role:id,name,slug', 'user:id,name,email']);
        if ($request->filled('company_id')) $query->where('company_id', $request->string('company_id')->toString());
        if ($request->filled('role_id')) $query->where('role_id', $request->string('role_id')->toString());
        return response()->json(['data' => $query->latest()->limit(100)->get(['id', 'tenant_id', 'role_id', 'user_id', 'company_id', 'branch_id', 'department_id', 'created_at'])]);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertManage($request, $authorization, $tenantId);
        $data = $request->validate(['user_id' => ['required', 'uuid'], 'role_id' => ['required', 'uuid'], 'company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid']]);
        $user = User::whereKey($data['user_id'])->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))->firstOrFail();
        $role = Role::where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->whereKey($data['role_id'])->with('permissions')->firstOrFail();
        $actorPermissions = RoleAssignment::where('tenant_id', $tenantId)->where('user_id', $request->user()->id)->with('role.permissions')->get()->flatMap(fn (RoleAssignment $assignment) => $assignment->role?->permissions?->pluck('name') ?? [])->unique();
        if ($role->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty()) throw ValidationException::withMessages(['role_id' => ['You cannot assign a role with permissions you do not hold.']]);
        $company = null;
        if (!empty($data['company_id'])) $company = Company::where('tenant_id', $tenantId)->where('is_active', true)->whereKey($data['company_id'])->firstOrFail();
        if ($company && ! $user->companyMemberships()->where('company_id', $company->id)->where('status', 'active')->exists()) throw ValidationException::withMessages(['company_id' => ['The user must have an active membership in the selected company.']]);
        if (!empty($data['branch_id'])) abort_unless($company && Branch::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['branch_id'])->exists(), 422, 'Branch must belong to the selected company.');
        if (!empty($data['department_id'])) abort_unless($company && Department::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['department_id'])->exists(), 422, 'Department must belong to the selected company.');
        $assignment = RoleAssignment::firstOrCreate(['tenant_id' => $tenantId, 'role_id' => $role->id, 'user_id' => $user->id, 'company_id' => $data['company_id'] ?? null, 'branch_id' => $data['branch_id'] ?? null, 'department_id' => $data['department_id'] ?? null]);
        if ($assignment->wasRecentlyCreated) $audit->record($request, 'role.assignment.created', $tenantId, $assignment);
        return response()->json(['data' => $assignment->load(['role:id,name,slug', 'user:id,name,email'])], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, RoleAssignment $assignment, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertManage($request, $authorization, $tenantId);
        abort_unless($assignment->tenant_id === $tenantId, 404);
        $assignment->delete();
        $audit->record($request, 'role.assignment.deleted', $tenantId, $assignment);
        return response()->json(['status' => 'deleted']);
    }

    private function assertManage(Request $request, AuthorizationService $authorization, string $tenantId): void { abort_unless($authorization->allows($request->user(), $tenantId, 'roles.manage'), 403); }
}
