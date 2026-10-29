<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\SubscriptionService;

function branchManagementFixture(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'organization.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Branch manager', 'slug' => 'branch-manager']);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    app(SubscriptionService::class)->provisionTrial($tenant);
    return [$user, $tenant, Company::create(['tenant_id' => $tenant->id, 'name' => 'Branch Co', 'code' => 'BRANCH-CO'])];
}

function assignBranchManager(Tenant $tenant, Company $company): User
{
    $manager = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $manager->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'organization.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Location manager', 'slug' => 'location-manager-'.fake()->unique()->numerify('####')]);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $manager->id, 'company_id' => $company->id]);
    return $manager;
}

it('creates a complete company-scoped branch and returns real summary data', function (): void {
    [$user, $tenant, $company] = branchManagementFixture();
    $manager = assignBranchManager($tenant, $company);

    $response = $this->actingAs($user)->postJson('/api/v1/companies/'.$company->id.'/branches', [
        'name' => 'Downtown', 'code' => 'DT-01', 'branch_type' => 'retail', 'opening_date' => '2026-01-15',
        'email' => 'downtown@example.test', 'phone' => '+95 1 555 0101', 'country' => 'MM', 'region' => 'Yangon',
        'city' => 'Yangon', 'township' => 'Botahtaung', 'street_address' => '1 Main Street', 'manager_user_id' => $manager->id,
        'timezone' => 'Asia/Yangon', 'document_number_prefix' => 'DT', 'status' => 'active',
    ], ['X-Tenant-Id' => $tenant->id])->assertCreated()->assertJsonPath('data.code', 'DT-01')->assertJsonPath('data.manager.id', $manager->id);

    $branchId = $response->json('data.id');
    $this->actingAs($user)->getJson('/api/v1/companies/'.$company->id.'/branches?per_page=10', ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('summary.total', 1)->assertJsonPath('summary.active', 1)->assertJsonPath('data.data.0.id', $branchId);
    $this->actingAs($user)->getJson('/api/v1/companies/'.$company->id.'/branches/'.$branchId, ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.branch.manager.id', $manager->id)->assertJsonPath('data.activity.0.action', 'branch.created');
});

it('rejects duplicate codes and unauthorized branch changes', function (): void {
    [$user, $tenant, $company] = branchManagementFixture();
    $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Existing', 'code' => 'DUP-01']);
    $this->actingAs($user)->postJson('/api/v1/companies/'.$company->id.'/branches', ['name' => 'Duplicate', 'code' => 'DUP-01'], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();

    $unauthorized = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $unauthorized->id, 'status' => 'active']);
    $this->actingAs($unauthorized)->patchJson('/api/v1/companies/'.$company->id.'/branches/'.$branch->id, ['name' => 'Nope'], ['X-Tenant-Id' => $tenant->id])->assertForbidden();
});

it('rejects inactive or unqualified branch managers', function (): void {
    [$user, $tenant, $company] = branchManagementFixture();
    $inactive = User::factory()->create(['status' => 'suspended']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $inactive->id, 'status' => 'active']);
    $this->actingAs($user)->postJson('/api/v1/companies/'.$company->id.'/branches', ['name' => 'Invalid manager', 'code' => 'INVALID-MGR', 'manager_user_id' => $inactive->id], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    $activeWithoutRole = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $activeWithoutRole->id, 'status' => 'active']);
    $this->actingAs($user)->postJson('/api/v1/companies/'.$company->id.'/branches', ['name' => 'Unqualified manager', 'code' => 'UNQUALIFIED-MGR', 'manager_user_id' => $activeWithoutRole->id], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
});

it('prevents cross-tenant and cross-company access', function (): void {
    [$user, $tenant, $company] = branchManagementFixture();
    $otherTenant = Tenant::factory()->create();
    $otherCompany = Company::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Co', 'code' => 'OTHER-CO']);
    $otherBranch = Branch::create(['tenant_id' => $otherTenant->id, 'company_id' => $otherCompany->id, 'name' => 'Other Branch', 'code' => 'OTHER-01']);

    $this->actingAs($user)->getJson('/api/v1/companies/'.$otherCompany->id.'/branches', ['X-Tenant-Id' => $tenant->id])->assertNotFound();
    $this->actingAs($user)->getJson('/api/v1/companies/'.$company->id.'/branches/'.$otherBranch->id, ['X-Tenant-Id' => $tenant->id])->assertNotFound();
});

it('enforces lifecycle rules and preserves archived branch records', function (): void {
    [$user, $tenant, $company] = branchManagementFixture();
    $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Archive Me', 'code' => 'ARCH-01']);
    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id.'/branches/'.$branch->id.'/status', ['status' => 'inactive'], ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.status', 'inactive');
    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id.'/branches/'.$branch->id.'/status', ['status' => 'archived'], ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.status', 'archived');
    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id.'/branches/'.$branch->id.'/status', ['status' => 'active'], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    expect(Branch::find($branch->id))->not->toBeNull()->and(Branch::find($branch->id)->status)->toBe('archived');
});

it('supports server-side search, status filtering, sorting, and pagination', function (): void {
    [$user, $tenant, $company] = branchManagementFixture();
    foreach (range(1, 12) as $number) Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => "Branch {$number}", 'code' => "B-{$number}", 'status' => $number === 12 ? 'inactive' : 'active', 'is_active' => $number !== 12]);
    $this->actingAs($user)->getJson('/api/v1/companies/'.$company->id.'/branches?search=Branch%2012&status=inactive&per_page=10&sort=name&direction=asc', ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.code', 'B-12');
});
