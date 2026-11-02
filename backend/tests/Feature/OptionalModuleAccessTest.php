<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantModule;
use App\Models\User;

it('lets platform administrators manage optional modules per tenant', function (): void {
    $admin = User::factory()->create(['is_platform_super_admin' => true]);
    $businessA = Tenant::factory()->create(['name' => 'Business A']);
    $businessB = Tenant::factory()->create(['name' => 'Business B']);

    $this->actingAs($admin)->putJson('/api/v1/platform/tenants/'.$businessA->id.'/modules', ['module_key' => 'pos', 'enabled' => true])->assertOk();
    $this->actingAs($admin)->putJson('/api/v1/platform/tenants/'.$businessA->id.'/modules', ['module_key' => 'projects', 'enabled' => true])->assertOk();
    $this->actingAs($admin)->putJson('/api/v1/platform/tenants/'.$businessA->id.'/modules', ['module_key' => 'projects', 'enabled' => false])->assertOk();

    expect(TenantModule::where('tenant_id', $businessA->id)->where('module_key', 'pos')->value('enabled'))->toBeTrue()
        ->and(TenantModule::where('tenant_id', $businessA->id)->where('module_key', 'projects')->value('enabled'))->toBeFalse()
        ->and(TenantModule::where('tenant_id', $businessB->id)->count())->toBe(0);
});

it('exposes disabled and enabled states only within the selected tenant', function (): void {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'status' => 'active']);
    TenantMembership::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'status' => 'active']);
    TenantModule::create(['tenant_id' => $tenantA->id, 'module_key' => 'pos', 'enabled' => true]);

    $this->actingAs($user)->withHeader('X-Tenant-Id', $tenantA->id)->getJson('/api/v1/modules')->assertOk()->assertJsonPath('data.0.module_key', 'pos')->assertJsonPath('data.0.status', 'enabled')->assertJsonPath('data.0.readiness', 'permission_required')->assertJsonPath('data.0.can_open', false);
    $this->actingAs($user)->withHeader('X-Tenant-Id', $tenantB->id)->getJson('/api/v1/modules')->assertOk()->assertJsonPath('data.0.module_key', 'pos')->assertJsonPath('data.0.status', 'disabled');
    $this->actingAs($user)->withHeader('X-Tenant-Id', $tenantB->id)->getJson('/api/v1/pos/registers')->assertNotFound();
});

it('does not allow ordinary users to change tenant modules', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', ['module_key' => 'pos', 'enabled' => true])->assertForbidden();
});
