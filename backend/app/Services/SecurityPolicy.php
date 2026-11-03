<?php

namespace App\Services;

use App\Models\SecuritySetting;
use App\Models\User;

final class SecurityPolicy
{
    public function enabled(string $key, bool $default): bool
    {
        $stored = SecuritySetting::query()->whereKey($key)->value('value');
        if (is_array($stored) && array_key_exists('value', $stored)) return (bool) $stored['value'];
        if (is_bool($stored)) return $stored;
        return $default;
    }

    public function platformMfaRequired(): bool
    {
        return $this->enabled('platform_mfa_required', (bool) config('security.platform_mfa_required'));
    }

    public function businessAdminMfaRequired(): bool
    {
        return $this->enabled('business_admin_mfa_required', false);
    }

    public function isBusinessAdministrator(User $user): bool
    {
        return $user->roleAssignments()
            ->whereHas('role.permissions', fn ($query) => $query->whereIn('name', ['organization.manage', 'members.invite']))
            ->whereHas('user.tenantMemberships', fn ($query) => $query->whereColumn('tenant_memberships.tenant_id', 'role_assignments.tenant_id')->where('status', 'active'))
            ->exists();
    }
}
