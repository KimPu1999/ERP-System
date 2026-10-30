<?php

use App\Models\{Company, CompanyMembership, Permission, Role, RoleAssignment, Supplier, Tenant, TenantMembership, User};

function scopedSupplierCreateFixture(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $companyA = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company A', 'code' => 'SC-A', 'base_currency' => 'MMK']);
    $companyB = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company B', 'code' => 'SC-B', 'base_currency' => 'MMK']);
    CompanyMembership::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);

    $permission = Permission::firstOrCreate(['name' => 'procurement.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Scoped Buyer', 'slug' => 'scoped-buyer-'.fake()->unique()->numerify('####')]);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id, 'company_id' => $companyA->id]);

    return compact('user', 'tenant', 'companyA', 'companyB');
}

it('lets a company-scoped procurement user discover its company and create suppliers', function (): void {
    $f = scopedSupplierCreateFixture();
    $headers = ['X-Tenant-Id' => $f['tenant']->id];

    $this->actingAs($f['user'])
        ->getJson('/api/v1/dashboard/companies', $headers)
        ->assertOk()
        ->assertJsonFragment(['id' => $f['companyA']->id])
        ->assertJsonMissing(['id' => $f['companyB']->id]);

    $this->actingAs($f['user'])
        ->postJson('/api/v1/suppliers', [
            'company_id' => $f['companyA']->id,
            'name' => 'Scoped Supplier',
            'email' => 'scoped@example.test',
            'supplier_type' => 'business',
        ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.company_id', $f['companyA']->id)
        ->assertJsonPath('data.name', 'Scoped Supplier');

    $this->actingAs($f['user'])
        ->getJson('/api/v1/suppliers?company_id='.$f['companyA']->id, $headers)
        ->assertOk()
        ->assertJsonPath('summary.total', 1);

    $this->actingAs($f['user'])
        ->getJson('/api/v1/suppliers/summary?company_id='.$f['companyA']->id, $headers)
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    expect(Supplier::where('tenant_id', $f['tenant']->id)->where('company_id', $f['companyA']->id)->count())->toBe(1);
});

it('rejects a scoped procurement user creating suppliers for other companies or globally', function (): void {
    $f = scopedSupplierCreateFixture();
    $headers = ['X-Tenant-Id' => $f['tenant']->id];

    $this->actingAs($f['user'])->postJson('/api/v1/suppliers', [
        'company_id' => $f['companyB']->id, 'name' => 'Unauthorized Supplier',
    ], $headers)->assertForbidden();

    $this->actingAs($f['user'])->postJson('/api/v1/suppliers', [
        'name' => 'Unscoped Supplier',
    ], $headers)->assertForbidden();

    $this->actingAs($f['user'])->getJson('/api/v1/suppliers?company_id='.$f['companyB']->id, $headers)->assertForbidden();
    $this->actingAs($f['user'])->getJson('/api/v1/suppliers', $headers)->assertForbidden();
    $this->actingAs($f['user'])->getJson('/api/v1/suppliers/summary?company_id='.$f['companyB']->id, $headers)->assertForbidden();

    expect(Supplier::where('tenant_id', $f['tenant']->id)->count())->toBe(0);
});
