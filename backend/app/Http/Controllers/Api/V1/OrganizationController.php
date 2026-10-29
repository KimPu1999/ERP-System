<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\PlatformAuditLog;
use App\Models\TenantMembership;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganizationController
{
    public function overview(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $companyId = $request->validate(['company_id' => ['required', 'uuid']])['company_id'];
        $company = Company::where('tenant_id', $tenantId)->whereKey($companyId)->firstOrFail();
        abort_unless($company->is_active, 403, 'Company is inactive.');
        abort_unless($this->allowed($authorization, $request, $tenantId, $companyId, ['organization.manage', 'company.manage', 'reports.view']), 403);

        $branches = Branch::where('tenant_id', $tenantId)->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'code', 'is_active']);
        $departments = Department::where('tenant_id', $tenantId)->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'code', 'branch_id']);
        $activity = PlatformAuditLog::query()->where('tenant_id', $tenantId)->whereIn('action', ['company.created', 'company.updated', 'company.logo.updated', 'company.document.created', 'company.document.deleted', 'branch.created', 'branch.updated', 'department.created'])->latest()->limit(8)->get(['id', 'action', 'created_at', 'metadata']);

        return response()->json(['data' => [
            'company' => $this->companyPayload($company),
            'kpis' => [
                'active_branches' => ['value' => $branches->where('is_active', true)->count(), 'available' => true],
                'departments' => ['value' => $departments->count(), 'available' => true],
                'active_members' => ['value' => TenantMembership::where('tenant_id', $tenantId)->where('status', 'active')->count(), 'available' => true],
                'teams' => ['value' => null, 'available' => false, 'reason' => 'Team management is not implemented yet.'],
            ],
            'branches' => $branches,
            'departments' => $departments,
            'hierarchy' => ['company' => ['id' => $company->id, 'name' => $company->trading_name ?: $company->name], 'branches' => $branches->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name, 'departments' => $departments->where('branch_id', $branch->id)->values()->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])->values()])->values()],
            'recent_activity' => $activity,
        ]]);
    }

    private function allowed(AuthorizationService $authorization, Request $request, ?string $tenantId, string $companyId, array $permissions): bool
    {
        foreach ($permissions as $permission) if ($tenantId && $authorization->allows($request->user(), $tenantId, $permission, $companyId)) return true;
        return false;
    }

    private function companyPayload(Company $company): array
    {
        return [...$company->toArray(), 'logo_url' => $company->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo_path) : null, 'cover_image_url' => $company->cover_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($company->cover_image_path) : null];
    }
}
