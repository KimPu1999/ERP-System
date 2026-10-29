<?php

use App\Models\{Permission, Role, RoleAssignment, Tenant, TenantMembership, User};

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
        ->assertJsonPath('data.currency.code', 'USD')
        ->assertJsonStructure(['data' => ['filters', 'currency', 'kpis', 'charts', 'recent_sales', 'top_products', 'inventory_alerts', 'pending_approvals']]);
});

it('requires dashboard permission and active tenant membership', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => $tenant->id])->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => Tenant::factory()->create()->id])->assertForbidden();
});
