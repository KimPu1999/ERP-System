<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BranchController
{
    public function index(Company $company): JsonResponse
    {
        $this->assertCompany($company);
        return response()->json(['data' => $company->branches()->orderBy('name')->paginate(25)]);
    }

    public function dashboardIndex(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) $request->header('X-Tenant-Id');
        abort_unless($company->tenant_id === $tenantId, 404);
        $hasCompanyScope = $authorization->allows($request->user(), $tenantId, 'reports.view', $company->id);
        $branchIds = $authorization->accessibleBranchIds($request->user(), $tenantId, 'reports.view');
        $query = $company->branches()->where('is_active', true)->orderBy('name');
        if (! $hasCompanyScope && is_array($branchIds)) $query->whereIn('id', $branchIds);
        if (! $hasCompanyScope && $branchIds === []) $query->whereRaw('1 = 0');
        return response()->json(['data' => $query->paginate(100)]);
    }

    public function store(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit, SubscriptionService $subscriptions): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($authorization->allows($request->user(), $company->tenant_id, 'organization.manage', $company->id), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40'], 'tenant_id' => ['prohibited'], 'company_id' => ['prohibited']]);
        $subscriptions->limit($company->tenant_id, 'branches', Branch::where('tenant_id', $company->tenant_id)->count());
        $branch = Branch::create([...$data, 'tenant_id' => $company->tenant_id, 'company_id' => $company->id]);
        $audit->record($request, 'branch.created', $branch->tenant_id, $branch, ['company_id' => $company->id]);
        return response()->json(['data' => $branch], 201);
    }

    public function update(Request $request, Branch $branch, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        abort_unless($branch->tenant_id === app(TenantContext::class)->id(), 404);
        abort_unless($request->user()->can('view', $branch), 403);
        abort_unless($authorization->allows($request->user(), $branch->tenant_id, 'organization.manage', $branch->company_id, $branch->id), 403);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:150'], 'is_active' => ['sometimes', 'boolean'], 'tenant_id' => ['prohibited'], 'company_id' => ['prohibited']]);
        $branch->update($data);
        $audit->record($request, 'branch.updated', $branch->tenant_id, $branch);
        return response()->json(['data' => $branch->fresh()]);
    }

    private function assertCompany(Company $company): void { abort_unless($company->tenant_id === (string) request()->header('X-Tenant-Id'), 404); abort_unless(request()->user()->can('view', $company), 403); }
}
