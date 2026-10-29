<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Support\TenantContext;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        return app(TenantContext::class)->id() === $branch->tenant_id
            && $user->tenantMemberships()->where('tenant_id', $branch->tenant_id)->where('status', 'active')->exists();
    }
}
