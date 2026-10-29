<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

function tenantFixture(): array
{
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'status' => 'active']);
    $companyB = Company::create(['tenant_id' => $tenantB->id, 'name' => 'Tenant B Company', 'code' => 'B']);
    $branchB = Branch::create(['tenant_id' => $tenantB->id, 'company_id' => $companyB->id, 'name' => 'Tenant B Branch', 'code' => 'B-1']);
    return [$user, $tenantA, $tenantB, $companyB, $branchB];
}

it('fails closed for an invalid tenant context', function (): void {
    [$user] = tenantFixture();
    $this->actingAs($user)->getJson('/api/v1/companies', ['X-Tenant-Id' => 'not-a-uuid'])->assertForbidden();
});

it('prevents tenant A from reading tenant B members and companies', function (): void {
    [$user, $tenantA, , $companyB] = tenantFixture();
    $this->actingAs($user)->getJson('/api/v1/members', ['X-Tenant-Id' => $tenantA->id])->assertOk()->assertJsonMissing(['id' => $companyB->id]);
    $this->actingAs($user)->getJson('/api/v1/companies/'.$companyB->id, ['X-Tenant-Id' => $tenantA->id])->assertNotFound();
});

it('prevents tenant A from updating tenant B branches', function (): void {
    [$user, $tenantA, , , $branchB] = tenantFixture();
    $this->actingAs($user)->patchJson('/api/v1/branches/'.$branchB->id, ['name' => 'Tampered'], ['X-Tenant-Id' => $tenantA->id])->assertNotFound();
});

it('does not let a platform super admin bypass tenant membership', function (): void {
    [$user, , $tenantB] = tenantFixture();
    $user->update(['is_platform_super_admin' => true]);
    $this->actingAs($user)->getJson('/api/v1/companies', ['X-Tenant-Id' => $tenantB->id])->assertForbidden();
});
