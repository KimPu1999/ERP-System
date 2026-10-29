<?php

use App\Models\{AccountingCurrency, Branch, Company, Customer, Permission, Role, RoleAssignment, SalesInvoice, Tenant, TenantMembership, User};

function dashboardUserWithReportAccess(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'reports.view']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Dashboard viewer', 'slug' => 'dashboard-viewer']);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    return [$user, $tenant];
}

it('returns a tenant-scoped dashboard overview with empty states', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview?period=last_30_days', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.currency.code', null)
        ->assertJsonPath('data.currency.configured', false)
        ->assertJsonStructure(['data' => ['filters', 'currency', 'kpis', 'charts', 'recent_sales', 'top_products', 'inventory_alerts', 'pending_approvals']]);
});

it('reconciles revenue to the selected company and branch', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $companyA = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company A', 'code' => 'A']);
    $companyB = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company B', 'code' => 'B']);
    $branchA = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'name' => 'Branch A', 'code' => 'A-1']);
    AccountingCurrency::create(['tenant_id' => $tenant->id, 'code' => 'MMK', 'name' => 'Myanmar Kyat', 'decimal_places' => 0, 'is_base' => true]);
    $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Customer', 'code' => 'C-1']);
    SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'branch_id' => $branchA->id, 'customer_id' => $customer->id, 'number' => 'INV-A', 'state' => 'issued', 'total' => '100', 'base_total' => '100']);
    SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'customer_id' => $customer->id, 'number' => 'INV-B', 'state' => 'issued', 'total' => '900', 'base_total' => '900']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview?period=last_30_days&company_id='.$companyA->id.'&branch_id='.$branchA->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.currency.code', 'MMK')
        ->assertJsonPath('data.kpis.revenue.value', '100.000000');
});

it('requires dashboard permission and active tenant membership', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => $tenant->id])->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => Tenant::factory()->create()->id])->assertForbidden();
});
