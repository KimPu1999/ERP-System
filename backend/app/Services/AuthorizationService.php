<?php

namespace App\Services;

use App\Models\User;
use App\Models\RoleAssignment;

final class AuthorizationService
{
    public function allows(User $user, string $tenantId, string $permission, ?string $companyId = null, ?string $branchId = null, ?string $departmentId = null): bool
    {
        if (! $user->tenantMemberships()->where('tenant_id', $tenantId)->where('status', 'active')->exists()) return false;
        return RoleAssignment::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where(function ($query) use ($companyId, $branchId, $departmentId): void {
            $query->whereNull('company_id')->orWhere('company_id', $companyId);
        })->where(function ($query) use ($branchId): void { $query->whereNull('branch_id')->orWhere('branch_id', $branchId); })->where(function ($query) use ($departmentId): void { $query->whereNull('department_id')->orWhere('department_id', $departmentId); })->whereHas('role.permissions', fn ($query) => $query->where('name', $permission))->exists();
    }
}
