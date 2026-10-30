<?php

use App\Models\{Company, Permission, Role, RoleAssignment, Tenant, TenantMembership, User};

function crmPhase3User(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permissions = [Permission::firstOrCreate(['name' => 'reports.view']), Permission::firstOrCreate(['name' => 'sales.manage'])];
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'CRM operator', 'slug' => 'crm-operator']);
    $role->permissions()->sync(collect($permissions)->pluck('id')->all());
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    return [$user, $tenant];
}

it('supports company-scoped customer creation and lead conversion', function (): void {
    [$user, $tenant] = crmPhase3User();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'CRM Company', 'code' => 'CRM']);

    $customer = $this->actingAs($user)->postJson('/api/v1/sales/customers', [
        'company_id' => $company->id, 'name' => 'Existing buyer', 'customer_type' => 'business',
    ], ['X-Tenant-Id' => $tenant->id])->assertCreated()->json('data');

    $lead = $this->actingAs($user)->postJson('/api/v1/sales/leads', [
        'company_id' => $company->id, 'name' => 'Qualified prospect', 'email' => 'prospect@example.test',
    ], ['X-Tenant-Id' => $tenant->id])->assertCreated()->json('data');

    $this->actingAs($user)->postJson('/api/v1/sales/leads/'.$lead['id'].'/convert', [
        'customer_id' => $customer['id'], 'idempotency_key' => 'crm-conversion-1',
    ], ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.customer.id', $customer['id']);

    $this->actingAs($user)->postJson('/api/v1/sales/leads/'.$lead['id'].'/convert', [
        'customer_id' => $customer['id'], 'idempotency_key' => 'crm-conversion-1',
    ], ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.customer.id', $customer['id']);
});

it('rejects CRM reads from another tenant', function (): void {
    [$user, $tenant] = crmPhase3User();
    $otherTenant = Tenant::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/sales/customers', ['X-Tenant-Id' => $otherTenant->id])->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/sales/crm/dashboard', ['X-Tenant-Id' => $otherTenant->id])->assertForbidden();
});
