<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Department;
use App\Models\Company;
use App\Models\Branch;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DepartmentController
{
    public function index(): JsonResponse { return response()->json(['data' => Department::query()->where('tenant_id', app(TenantContext::class)->id())->orderBy('name')->paginate(25)]); }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'organization.manage'), 403); $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40'], 'company_id' => ['required', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'tenant_id' => ['prohibited']]); abort_unless(Company::whereKey($data['company_id'])->where('tenant_id', $tenantId)->exists(), 404); if (! empty($data['branch_id'])) abort_unless(Branch::whereKey($data['branch_id'])->where('tenant_id', $tenantId)->where('company_id', $data['company_id'])->exists(), 404); $department = Department::create([...$data, 'tenant_id' => $tenantId]); $audit->record($request, 'department.created', $tenantId, $department); return response()->json(['data' => $department], 201); }
}
