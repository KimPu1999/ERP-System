<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('rejects invalid credentials without creating a session', function (): void {
    $user = User::factory()->create(['email' => 'auth@example.test']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
    expect(auth()->check())->toBeFalse();
});

it('blocks suspended accounts at login', function (): void {
    $user = User::factory()->create(['status' => 'suspended', 'suspended_at' => now(), 'password' => 'secret-password']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'])->assertStatus(423);
});

it('keeps platform and business login contexts separate', function (): void {
    $business = User::factory()->create(['password' => 'secret-password']);
    $platform = User::factory()->create(['is_platform_super_admin' => true, 'password' => 'secret-password']);

    $this->postJson('/api/v1/auth/platform/login', ['email' => $business->email, 'password' => 'secret-password'])
        ->assertForbidden();
    $this->postJson('/api/v1/auth/business/login', ['email' => $platform->email, 'password' => 'secret-password'])
        ->assertForbidden();
});

it('requires a verified active platform administrator for platform login', function (): void {
    $unverified = User::factory()->create(['is_platform_super_admin' => true, 'email_verified_at' => null, 'password' => 'secret-password']);
    $this->postJson('/api/v1/auth/platform/login', ['email' => $unverified->email, 'password' => 'secret-password'])
        ->assertForbidden();

    $admin = User::factory()->create(['is_platform_super_admin' => true, 'password' => 'secret-password']);
    $this->postJson('/api/v1/auth/platform/login', ['email' => $admin->email, 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id);
});

it('provisions platform access only for an existing verified active user', function (): void {
    $unverified = User::factory()->create(['email' => 'unverified-platform@example.test', 'email_verified_at' => null]);

    expect(Artisan::call('platform:provision-admin', ['email' => $unverified->email, '--yes' => true]))->toBe(1);
    expect($unverified->fresh()->is_platform_super_admin)->toBeFalse();

    $verified = User::factory()->create(['email' => 'verified-platform@example.test']);

    expect(Artisan::call('platform:provision-admin', ['email' => $verified->email, '--yes' => true]))->toBe(0);
    expect($verified->fresh()->is_platform_super_admin)->toBeTrue();
    expect(PlatformAuditLog::where('action', 'platform.admin.provisioned')->where('auditable_id', $verified->id)->exists())->toBeTrue();
});

it('requires an active business membership while preserving pending invitation onboarding', function (): void {
    $unassigned = User::factory()->create(['password' => 'secret-password']);
    $this->postJson('/api/v1/auth/business/login', ['email' => $unassigned->email, 'password' => 'secret-password'])
        ->assertForbidden();

    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $unassigned->id, 'status' => 'active']);
    $this->postJson('/api/v1/auth/business/login', ['email' => $unassigned->email, 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonMissing(['workspace_access_required' => true]);
});

it('requires authentication for session management', function (): void {
    $this->getJson('/api/v1/auth/sessions')->assertUnauthorized();
});

it('does not allow an invitation to be accepted by another email', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create(['email' => 'different@example.test']);
    $this->actingAs($other)->postJson('/api/v1/invitations/accept', ['invitation_id' => '00000000-0000-0000-0000-000000000000', 'token' => 'invalid'])->assertNotFound();
    expect($owner->id)->not->toBe($other->id);
});

it('does not expose platform tenant management to ordinary users', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/platform/tenants')->assertForbidden();
});

it('requires authentication for workspace access state', function (): void {
    $this->getJson('/api/v1/workspace-access')->assertUnauthorized();
});

it('offers verified users without memberships the workspace setup flow', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/workspace-access')->assertOk();

    expect($response->json('data.active_workspaces'))->toBe([])
        ->and($response->json('data.restricted_workspaces'))->toBe([])
        ->and($response->json('data.can_create_workspace'))->toBeTrue();
});

it('creates a workspace once and returns it for a retried onboarding request', function (): void {
    $user = User::factory()->create(['email' => 'workspace-owner@example.test']);
    $payload = ['name' => 'Acme Workspace', 'slug' => 'acme-workspace'];

    $this->actingAs($user)->postJson('/api/v1/tenants', $payload)->assertCreated()->assertJsonPath('already_exists', false);
    $this->actingAs($user)->postJson('/api/v1/tenants', $payload)->assertOk()->assertJsonPath('already_exists', true);

    expect(TenantMembership::where('user_id', $user->id)->count())->toBe(1);
    expect(Tenant::where('slug', $payload['slug'])->count())->toBe(1);
});

it('returns only active workspace memberships and classifies restricted access', function (): void {
    $user = User::factory()->create();
    $active = Tenant::factory()->create(['name' => 'Active workspace']);
    $suspended = Tenant::factory()->create(['name' => 'Suspended workspace', 'status' => 'suspended']);
    TenantMembership::create(['tenant_id' => $active->id, 'user_id' => $user->id, 'status' => 'active']);
    TenantMembership::create(['tenant_id' => $suspended->id, 'user_id' => $user->id, 'status' => 'active']);

    $response = $this->actingAs($user)->getJson('/api/v1/workspace-access')->assertOk();

    expect($response->json('data.active_workspaces.0.name'))->toBe('Active workspace')
        ->and($response->json('data.restricted_workspaces.0.status'))->toBe('tenant_inactive');
});

it('blocks tenant API access when the workspace is suspended', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['status' => 'suspended']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)->withHeader('X-Tenant-Id', $tenant->id)->getJson('/api/v1/companies')->assertForbidden();
});

it('exposes matching pending invitations without exposing invitation tokens', function (): void {
    $user = User::factory()->create(['email' => 'invitee@example.test']);
    $tenant = Tenant::factory()->create();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Invitee company', 'code' => 'INV', 'is_active' => true]);
    app(TenantContext::class)->set($tenant->id);
    try {
        Invitation::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'inviter_user_id' => User::factory()->create()->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
        ]);
    } finally {
        app(TenantContext::class)->clear();
    }

    $response = $this->actingAs($user)->getJson('/api/v1/workspace-access')->assertOk();

    expect($response->json('data.pending_invitations.0.company_name'))->toBe('Invitee company')
        ->and($response->json('data.pending_invitations.0.token'))->toBeNull()
        ->and($response->json('data.can_create_workspace'))->toBeFalse();
});

it('accepts a matching invitation without requiring a preselected tenant', function (): void {
    $user = User::factory()->create(['email' => 'accept@example.test']);
    $tenant = Tenant::factory()->create();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Accept company', 'code' => 'ACC', 'is_active' => true]);
    $token = Str::random(64);
    app(TenantContext::class)->set($tenant->id);
    try {
        $invitation = Invitation::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'inviter_user_id' => User::factory()->create()->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);
    } finally {
        app(TenantContext::class)->clear();
    }

    $this->actingAs($user)->postJson('/api/v1/invitations/accept', ['invitation_id' => $invitation->id, 'token' => $token])->assertOk();

    expect(TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('status', 'active')->exists())->toBeTrue();
});

it('does not require company membership for platform administrators', function (): void {
    $admin = User::factory()->create(['is_platform_super_admin' => true]);

    $response = $this->actingAs($admin)->getJson('/api/v1/workspace-access')->assertOk();

    expect($response->json('data.platform_admin'))->toBeTrue()
        ->and($response->json('data.can_create_workspace'))->toBeFalse();
});
