<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\RoleAssignment;
use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleAssignmentController
{
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'roles.manage'), 403); $data = $request->validate(['user_id' => ['required', 'uuid'], 'role_id' => ['required', 'uuid'], 'company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid']]); abort_unless(User::whereKey($data['user_id'])->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))->exists(), 404); abort_unless(Role::whereKey($data['role_id'])->where('tenant_id', $tenantId)->exists(), 404); if (! empty($data['company_id'])) abort_unless(Company::whereKey($data['company_id'])->where('tenant_id', $tenantId)->exists(), 404); if (! empty($data['branch_id'])) abort_unless(Branch::whereKey($data['branch_id'])->where('tenant_id', $tenantId)->exists(), 404); if (! empty($data['department_id'])) abort_unless(Department::whereKey($data['department_id'])->where('tenant_id', $tenantId)->exists(), 404); $assignment = RoleAssignment::create([...$data, 'tenant_id' => $tenantId]); $audit->record($request, 'role.assignment.created', $tenantId, $assignment); return response()->json(['data' => $assignment], 201); }
}
