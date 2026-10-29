<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\TenantContext;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return app(TenantContext::class)->id() === $company->tenant_id
            && $user->tenantMemberships()->where('tenant_id', $company->tenant_id)->where('status', 'active')->exists();
    }
}
