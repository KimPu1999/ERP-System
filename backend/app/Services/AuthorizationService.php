<?php

namespace App\Services;

use App\Models\RoleAssignment;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Models\Company;

final class AuthorizationService
{
    public function allows(User $user, ?string $tenantId, string $permission, ?string $companyId = null, ?string $branchId = null, ?string $departmentId = null): bool
    {
        if (! $tenantId) return false;
        if (! $user->tenantMemberships()->where('tenant_id', $tenantId)->where('status', 'active')->exists()) return false;
        if ($companyId && ! Company::whereKey($companyId)->where('tenant_id', $tenantId)->where('is_active', true)->exists()) return false;
        $assignments = $this->permissionAssignments($user, $tenantId, $permission);

        // A tenant-wide role assignment is already an explicit authorization
        // scope. Requiring a second CompanyMembership record here made valid
        // tenant-wide administrators unable to access their tenant's active
        // companies, while still allowing company-scoped assignments to be
        // checked strictly below.
        if ((clone $assignments)->whereNull('company_id')->whereNull('branch_id')->whereNull('department_id')->exists()) {
            return true;
        }

        $scopedAssignments = $assignments->where(function ($query) use ($companyId): void {
            $query->whereNull('company_id')->orWhere('company_id', $companyId);
        })->where(function ($query) use ($branchId): void {
            $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
        })->where(function ($query) use ($departmentId): void {
            $query->whereNull('department_id')->orWhere('department_id', $departmentId);
        });

        // A company-scoped role assignment is itself the explicit company
        // authorization. CompanyMembership remains relevant to membership
        // workflows, but must not invalidate an authorized scoped role.
        if ($companyId && (clone $scopedAssignments)->where('company_id', $companyId)->exists()) return true;
        if ($companyId && ! CompanyMembership::where('company_id', $companyId)->where('user_id', $user->id)->where('tenant_id', $tenantId)->where('status', 'active')->exists()) return false;

        return $scopedAssignments->exists();
    }

    public function accessibleCompanyIds(User $user, string $tenantId, string $permission): ?array
    {
        $assignments = $this->permissionAssignments($user, $tenantId, $permission);
        if ((clone $assignments)->whereNull('company_id')->whereNull('branch_id')->exists()) return null;
        $companyIds = $assignments->whereNotNull('company_id')->pluck('company_id')->unique()->values()->all();
        return Company::where('tenant_id', $tenantId)->where('is_active', true)->whereIn('id', $companyIds)->pluck('id')->all();
    }

    public function accessibleBranchIds(User $user, string $tenantId, string $permission): ?array
    {
        $assignments = $this->permissionAssignments($user, $tenantId, $permission);
        if ((clone $assignments)->whereNull('company_id')->whereNull('branch_id')->exists()) return null;
        return $assignments->whereNotNull('branch_id')->pluck('branch_id')->unique()->values()->all();
    }

    private function permissionAssignments(User $user, string $tenantId, string $permission)
    {
        return RoleAssignment::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->whereHas('role.permissions', fn ($query) => $query->where('name', $permission));
    }
}
