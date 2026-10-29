<?php

namespace App\Services;

use App\Models\RoleAssignment;
use App\Models\User;

final class AuthorizationService
{
    public function allows(User $user, string $tenantId, string $permission, ?string $companyId = null, ?string $branchId = null, ?string $departmentId = null): bool
    {
        if (! $user->tenantMemberships()->where('tenant_id', $tenantId)->where('status', 'active')->exists()) return false;
        return $this->permissionAssignments($user, $tenantId, $permission)->where(function ($query) use ($companyId): void {
            $query->whereNull('company_id')->orWhere('company_id', $companyId);
        })->where(function ($query) use ($branchId): void {
            $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
        })->where(function ($query) use ($departmentId): void {
            $query->whereNull('department_id')->orWhere('department_id', $departmentId);
        })->exists();
    }

    public function accessibleCompanyIds(User $user, string $tenantId, string $permission): ?array
    {
        $assignments = $this->permissionAssignments($user, $tenantId, $permission);
        if ((clone $assignments)->whereNull('company_id')->whereNull('branch_id')->exists()) return null;
        return $assignments->whereNotNull('company_id')->pluck('company_id')->unique()->values()->all();
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
