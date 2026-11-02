<?php

use App\Models\{Company, Invitation, Permission, Role, RoleAssignment, Tenant, TenantMembership, User};
use Illuminate\Support\Facades\Mail;

function platformBusinessFixture(): array
{
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $tenant = Tenant::factory()->create(['status' => 'pending']);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Platform Business', 'code' => 'PLAT']);
    $permissions = collect(['organization.manage', 'members.invite'])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]));
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Business Owner', 'slug' => 'business-owner']);
    $role->permissions()->sync($permissions->pluck('id')->all());
    $owner = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'status' => 'active']);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $owner->id]);
    return compact('platform', 'tenant', 'company', 'role', 'owner');
}

it('keeps platform business management behind verified platform authorization', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/platform/summary')->assertForbidden();

    $f = platformBusinessFixture();
    $this->actingAs($f['platform'])->getJson('/api/v1/platform/summary')->assertOk()->assertJsonPath('summary.pending_approvals', 1);
});

it('creates, approves, suspends and restores a business without deleting its records', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $created = $this->actingAs($platform)->postJson('/api/v1/platform/tenants', ['name' => 'New Business', 'slug' => 'new-business'])->assertCreated()->assertJsonPath('data.status', 'pending');
    $tenantId = $created->json('data.id');

    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenantId.'/status', ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenantId.'/status', ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenantId.'/status', ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');

    expect(Tenant::find($tenantId)->status)->toBe('active');
    expect(\App\Models\TenantSubscription::where('tenant_id', $tenantId)->exists())->toBeTrue();
    expect(\App\Models\PlatformAuditLog::where('action', 'platform.tenant.status_changed')->where('tenant_id', $tenantId)->count())->toBe(3);
});

it('invites business administrators with a hashed single-use token and protects the last administrator', function (): void {
    Mail::fake();
    $f = platformBusinessFixture();
    $this->actingAs($f['platform'])->postJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/administrators/invitations', ['email' => 'new-admin@example.test', 'company_id' => $f['company']->id, 'role_id' => $f['role']->id])->assertCreated()->assertJsonMissingPath('data.token');
    $invitation = Invitation::where('email', 'new-admin@example.test')->firstOrFail();
    expect($invitation->token_hash)->toHaveLength(64);
    Mail::assertSent(\App\Mail\CompanyInvitationMail::class);

    $membership = TenantMembership::where('tenant_id', $f['tenant']->id)->where('user_id', $f['owner']->id)->firstOrFail();
    $this->actingAs($f['platform'])->patchJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/memberships/'.$membership->id, ['status' => 'suspended'])->assertUnprocessable();
    expect($membership->fresh()->status)->toBe('active');
});

it('allows a platform administrator to update an administrator membership when another owner remains', function (): void {
    $f = platformBusinessFixture();
    $second = User::factory()->create();
    TenantMembership::create(['tenant_id' => $f['tenant']->id, 'user_id' => $second->id, 'status' => 'active']);
    RoleAssignment::create(['tenant_id' => $f['tenant']->id, 'role_id' => $f['role']->id, 'user_id' => $second->id]);
    $membership = TenantMembership::where('tenant_id', $f['tenant']->id)->where('user_id', $second->id)->firstOrFail();

    $this->actingAs($f['platform'])->patchJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/memberships/'.$membership->id, ['status' => 'revoked'])->assertOk()->assertJsonPath('data.status', 'revoked');
    expect(\App\Models\PlatformAuditLog::where('action', 'platform.business_admin.membership_updated')->where('tenant_id', $f['tenant']->id)->exists())->toBeTrue();
});
