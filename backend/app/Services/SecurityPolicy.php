<?php

namespace App\Services;

use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Database\QueryException;

final class SecurityPolicy
{
    public function enabled(string $key, bool $default): bool
    {
        try {
            $stored = SecuritySetting::query()->whereKey($key)->value('value');
        } catch (QueryException $exception) {
            // During deploys, an older application process can briefly run
            // before the settings migration has been applied. Fall back to
            // the environment policy instead of turning login into a 500.
            if (! str_contains($exception->getMessage(), 'security_settings')) {
                throw $exception;
            }

            $stored = null;
        }
        if (is_array($stored) && array_key_exists('value', $stored)) return (bool) $stored['value'];
        if (is_bool($stored)) return $stored;
        return $default;
    }

    public function platformMfaRequired(): bool
    {
        // Platform MFA is an environment-controlled security boundary. Do not
        // allow a database setting to weaken it outside the approved local mode.
        return (bool) config('security.platform_mfa_required', true);
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
