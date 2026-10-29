<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\PlatformAuditLog;
use App\Models\Team;
use App\Models\User;
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

        $branches = Branch::where('tenant_id', $tenantId)->where('company_id', $companyId)->where('status', '!=', 'archived')->with('manager:id,name')->withCount(['employees as staff_count' => fn ($query) => $query->where('status', 'active')])->orderBy('name')->get(['id', 'name', 'code', 'is_active', 'status', 'manager_user_id']);
        $departments = Department::where('tenant_id', $tenantId)->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'code', 'branch_id']);
        $departmentStaff = Employee::query()->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('status', 'active')->whereNotNull('department_id')->selectRaw('department_id, count(*) as staff_count')->groupBy('department_id')->pluck('staff_count', 'department_id');
        $activeMembers = CompanyMembership::query()->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('status', 'active')->distinct('user_id')->count('user_id');
        $suspendedMembers = CompanyMembership::query()->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('status', 'suspended')->distinct('user_id')->count('user_id');
        $pendingInvitations = Invitation::query()->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('status', 'pending')->where('expires_at', '>', now())->count();
        $activeTeams = Team::query()->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('is_active', true)->count();
        $teams = Team::query()->where('tenant_id', $tenantId)->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $activity = PlatformAuditLog::query()->where('tenant_id', $tenantId)->whereIn('action', ['company.created', 'company.updated', 'company.logo.updated', 'company.document.created', 'company.document.deleted', 'branch.created', 'branch.updated', 'department.created', 'department.updated', 'invitation.created', 'invitation.sent', 'role.assignment.created', 'staff.assignment.updated'])->latest()->limit(8)->get(['id', 'action', 'actor_user_id', 'auditable_type', 'auditable_id', 'created_at', 'metadata']);
        $actorNames = User::whereIn('id', $activity->pluck('actor_user_id')->filter()->unique())->pluck('name', 'id');

        return response()->json(['data' => [
            'company' => $this->companyPayload($company),
            'kpis' => [
                'total_branches' => ['value' => $branches->count(), 'available' => true],
                'active_branches' => ['value' => $branches->where('is_active', true)->count(), 'available' => true],
                'active_departments' => ['value' => $departments->count(), 'available' => true],
                'departments' => ['value' => $departments->count(), 'available' => true],
                'business_units' => ['value' => null, 'available' => false, 'reason' => 'Business unit management is not implemented yet.'],
                'active_members' => ['value' => $activeMembers, 'available' => true],
                'suspended_members' => ['value' => $suspendedMembers, 'available' => true],
                'teams' => ['value' => $activeTeams, 'available' => true],
                'pending_invitations' => ['value' => $pendingInvitations, 'available' => true],
                'access_review' => ['value' => null, 'available' => false, 'reason' => 'Access review queue is not implemented yet.'],
            ],
            'branches' => $branches->map(fn (Branch $branch) => [...$branch->toArray(), 'manager_name' => $branch->manager?->name]),
            'departments' => $departments->map(fn (Department $department) => [...$department->toArray(), 'manager_name' => null, 'staff_count' => (int) ($departmentStaff[$department->id] ?? 0), 'status' => null]),
            'hierarchy' => ['company' => ['id' => $company->id, 'name' => $company->trading_name ?: $company->name], 'branches' => $branches->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name, 'departments' => $departments->where('branch_id', $branch->id)->values()->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])->values()])->values(), 'teams' => $teams],
            'recent_activity' => $activity->map(fn (PlatformAuditLog $item) => [...$item->toArray(), 'actor_name' => $item->actor_user_id ? ($actorNames[$item->actor_user_id] ?? 'Authorized user') : 'System', 'entity' => $this->entityLabel($item->auditable_type), 'status' => 'recorded']),
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

    private function entityLabel(?string $type): ?string
    {
        if (! $type) return null;
        $parts = explode('\\', $type);
        return preg_replace('/(?<!^)([A-Z])/', ' $1', end($parts)) ?: null;
    }
}
