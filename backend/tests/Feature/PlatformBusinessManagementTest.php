<?php

use Carbon\CarbonImmutable;
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

it('returns live platform overview metrics and activity only to platform administrators', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    User::factory()->create(['is_platform_super_admin' => false]);
    Tenant::factory()->create(['status' => 'pending', 'name' => 'Overview business']);

    $response = $this->actingAs($platform)->getJson('/api/v1/platform/summary')->assertOk();

    $response->assertJsonStructure([
        'summary' => [
            'total_businesses', 'active_businesses', 'pending_approvals',
            'total_platform_users', 'active_subscriptions',
            'system_alerts' => ['total', 'pending_businesses', 'past_due_subscriptions', 'failed_logins_last_24h'],
            'business_growth', 'recent_registrations', 'recent_activities',
        ],
    ]);
    expect($response->json('summary.total_businesses'))->toBe(1)
        ->and($response->json('summary.pending_approvals'))->toBe(1)
        ->and($response->json('summary.total_platform_users'))->toBe(1)
        ->and($response->json('summary.system_alerts.pending_businesses'))->toBe(1);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/summary')->assertForbidden();
});

it('returns live platform analytics only to platform administrators', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    User::factory()->create(['is_platform_super_admin' => false]);
    Tenant::factory()->create(['status' => 'pending']);
    Tenant::factory()->create(['status' => 'active']);

    $response = $this->actingAs($platform)->getJson('/api/v1/platform/analytics')->assertOk();
    $response->assertJsonStructure(['data' => ['period', 'business_growth', 'businesses_by_status', 'subscriptions_by_state', 'platform_users_by_status']]);

    $statuses = collect($response->json('data.businesses_by_status'))->keyBy('status');
    expect($response->json('data.business_growth'))->toHaveCount(6)
        ->and($statuses->get('pending')['count'] ?? 0)->toBe(1)
        ->and($statuses->get('active')['count'] ?? 0)->toBe(1)
        ->and(collect($response->json('data.platform_users_by_status'))->sum('count'))->toBe(1);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/analytics')->assertForbidden();
});

it('applies reporting timezone and custom date boundaries to growth metrics', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $reportingDay = CarbonImmutable::now('Asia/Yangon')->subDays(2)->startOfDay();

    Tenant::factory()->create([
        'created_at' => $reportingDay->addHours(2)->setTimezone('UTC'),
        'updated_at' => $reportingDay->addHours(2)->setTimezone('UTC'),
    ]);
    Tenant::factory()->create([
        'created_at' => $reportingDay->subDay()->addHours(2)->setTimezone('UTC'),
        'updated_at' => $reportingDay->subDay()->addHours(2)->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($platform)->getJson('/api/v1/platform/summary?period=custom&granularity=daily&from='.$reportingDay->format('Y-m-d').'&to='.$reportingDay->format('Y-m-d'))->assertOk();

    expect($response->json('summary.reporting_timezone'))->toBe('Asia/Yangon')
        ->and($response->json('summary.filters.period'))->toBe('custom')
        ->and($response->json('summary.filters.granularity'))->toBe('daily')
        ->and($response->json('summary.business_growth'))->toHaveCount(1)
        ->and($response->json('summary.business_growth.0.bucket'))->toBe($reportingDay->format('Y-m-d'))
        ->and($response->json('summary.business_growth.0.count'))->toBe(1);
});

it('exposes protected platform health checks without leaking configuration secrets', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);

    $this->getJson('/api/v1/platform/health')->assertUnauthorized();

    $response = $this->actingAs($platform)->getJson('/api/v1/platform/health')->assertOk();
    $response->assertJsonStructure(['data' => ['status', 'checked_at', 'checks' => ['database', 'cache', 'queue', 'mail', 'scheduler']]])
        ->assertJsonMissing(['APP_KEY', 'password', 'token']);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/health')->assertForbidden();
});

it('rejects incomplete custom reporting ranges', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);

    $this->actingAs($platform)->getJson('/api/v1/platform/summary?period=custom&from=2026-01-01')->assertUnprocessable();
    $this->actingAs($platform)->getJson('/api/v1/platform/analytics?period=custom&from=2026-01-03&to=2026-01-01')->assertUnprocessable();
});

it('protects platform audit and security event views from business users', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    \App\Models\PlatformAuditLog::create(['actor_user_id' => $platform->id, 'action' => 'platform.test', 'metadata' => ['safe' => true]]);
    \App\Models\SecurityEvent::create(['user_id' => $platform->id, 'event' => 'auth.test', 'metadata' => ['safe' => true]]);

    $this->actingAs($platform)->getJson('/api/v1/platform/audit-logs')->assertOk()->assertJsonPath('data.data.0.action', 'platform.test');
    $this->actingAs($platform)->getJson('/api/v1/platform/security/events')->assertOk()->assertJsonPath('data.data.0.event', 'auth.test');

    $business = User::factory()->create();
    $this->actingAs($business)->getJson('/api/v1/platform/audit-logs')->assertForbidden();
    $this->actingAs($business)->getJson('/api/v1/platform/security/events')->assertForbidden();
});

it('lists platform users and protects the last active administrator', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $second = User::factory()->create(['is_platform_super_admin' => true]);

    $this->actingAs($platform)->getJson('/api/v1/platform/users')->assertOk()->assertJsonPath('summary.total', 2);
    $this->actingAs($platform)->patchJson('/api/v1/platform/users/'.$second->id.'/status', ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->actingAs($platform)->patchJson('/api/v1/platform/users/'.$platform->id.'/status', ['status' => 'suspended'])->assertUnprocessable();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/users')->assertForbidden();
});

it('exposes only safe platform settings to authorized administrators', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $response = $this->actingAs($platform)->getJson('/api/v1/platform/settings')->assertOk();
    $response->assertJsonStructure(['data' => ['authentication' => ['email_verification_required', 'mfa_capable', 'session_encrypted', 'session_secure_cookie'], 'security' => ['debug_mode', 'platform_authorization', 'tenant_isolation']]]);
    expect($response->json('data.authentication.email_verification_required'))->toBeTrue();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/settings')->assertForbidden();
});

it('creates, approves, suspends and restores a business without deleting its records', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $created = $this->actingAs($platform)->postJson('/api/v1/platform/tenants', ['name' => 'New Business', 'slug' => 'new-business'])->assertCreated()->assertJsonPath('data.status', 'pending');
    $tenantId = $created->json('data.id');

    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenantId.'/status', ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenantId.'/status', ['status' => 'suspended', 'reason' => 'Security review'])->assertOk()->assertJsonPath('data.status', 'suspended');
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

it('creates the default company and owner invitation from business registration data', function (): void {
    Mail::fake();
    $platform = User::factory()->create(['is_platform_super_admin' => true]);

    $response = $this->actingAs($platform)->postJson('/api/v1/platform/tenants', [
        'name' => 'Yangon Retail Group',
        'slug' => 'yangon-retail-group',
        'owner_email' => 'owner@yangon-retail.test',
        'business_email' => 'hello@yangon-retail.test',
        'contact_phone' => '+95 1 555 0101',
        'country_code' => 'MM',
        'timezone' => 'Asia/Yangon',
        'base_currency' => 'MMK',
        'business_type' => 'Retail',
        'registration_number' => 'REG-001',
    ])->assertCreated();

    $tenantId = $response->json('data.id');
    expect(Company::where('tenant_id', $tenantId)->where('email', 'hello@yangon-retail.test')->where('base_currency', 'MMK')->exists())->toBeTrue();
    expect(Invitation::where('tenant_id', $tenantId)->where('email', 'owner@yangon-retail.test')->where('status', 'pending')->exists())->toBeTrue();
    Mail::assertSent(\App\Mail\CompanyInvitationMail::class);
});

it('requires a reason for suspension and rejects invalid business transitions', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $tenant = Tenant::factory()->create(['status' => 'pending']);
    Company::create(['tenant_id' => $tenant->id, 'name' => 'Transition Business', 'code' => 'TRANS']);

    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenant->id.'/status', ['status' => 'suspended'])->assertUnprocessable();
    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenant->id.'/status', ['status' => 'active'])->assertOk();
    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/'.$tenant->id.'/status', ['status' => 'pending'])->assertUnprocessable();
});

it('transfers ownership only to an active verified administrator', function (): void {
    $f = platformBusinessFixture();
    $manager = User::factory()->create(['email_verified_at' => now()]);
    TenantMembership::create(['tenant_id' => $f['tenant']->id, 'user_id' => $manager->id, 'status' => 'active']);
    $managerRole = Role::create(['tenant_id' => $f['tenant']->id, 'name' => 'Business Manager', 'slug' => 'business-manager']);
    $managerRole->permissions()->sync([Permission::where('name', 'members.invite')->firstOrFail()->id]);
    RoleAssignment::create(['tenant_id' => $f['tenant']->id, 'role_id' => $managerRole->id, 'user_id' => $manager->id]);
    $source = TenantMembership::where('tenant_id', $f['tenant']->id)->where('user_id', $f['owner']->id)->firstOrFail();
    $target = TenantMembership::where('tenant_id', $f['tenant']->id)->where('user_id', $manager->id)->firstOrFail();

    $this->actingAs($f['platform'])->postJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/ownership-transfer', ['from_membership_id' => $source->id, 'to_membership_id' => $target->id, 'replacement_role_id' => $managerRole->id])->assertOk();
    expect(RoleAssignment::where('tenant_id', $f['tenant']->id)->where('user_id', $f['owner']->id)->value('role_id'))->toBe($managerRole->id);
    expect(RoleAssignment::where('tenant_id', $f['tenant']->id)->where('user_id', $manager->id)->value('role_id'))->toBe($f['role']->id);
});

it('keeps business lifecycle changes explicit and rejects with a reason', function (): void {
    Mail::fake();
    $f = platformBusinessFixture();
    $invitation = Invitation::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'inviter_user_id' => $f['platform']->id, 'role_id' => $f['role']->id, 'email' => 'pending@example.test', 'token_hash' => hash('sha256', 'pending-token'), 'expires_at' => now()->addDay(), 'status' => 'pending']);

    $this->actingAs($f['platform'])->putJson('/api/v1/platform/tenants/'.$f['tenant']->id, ['name' => 'Changed', 'status' => 'active'])->assertUnprocessable();
    $this->actingAs($f['platform'])->patchJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/status', ['status' => 'rejected'])->assertUnprocessable();
    $this->actingAs($f['platform'])->patchJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/status', ['status' => 'rejected', 'reason' => 'Incomplete registration'])->assertOk()->assertJsonPath('data.status', 'rejected');
    expect($invitation->fresh()->status)->toBe('revoked');
    $this->actingAs($f['platform'])->patchJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/status', ['status' => 'active'])->assertUnprocessable();
    $this->actingAs($f['platform'])->patchJson('/api/v1/platform/tenants/'.$f['tenant']->id.'/status', ['status' => 'pending'])->assertOk();
});

it('does not reactivate revoked memberships and prevents cross-business invitation access', function (): void {
    Mail::fake();
    $first = platformBusinessFixture();
    $second = platformBusinessFixture();
    $membership = TenantMembership::where('tenant_id', $first['tenant']->id)->where('user_id', $first['owner']->id)->firstOrFail();
    $secondAdmin = User::factory()->create();
    TenantMembership::create(['tenant_id' => $first['tenant']->id, 'user_id' => $secondAdmin->id, 'status' => 'active']);
    RoleAssignment::create(['tenant_id' => $first['tenant']->id, 'role_id' => $first['role']->id, 'user_id' => $secondAdmin->id]);
    $this->actingAs($first['platform'])->patchJson('/api/v1/platform/tenants/'.$first['tenant']->id.'/memberships/'.$membership->id, ['status' => 'revoked'])->assertOk();
    $this->actingAs($first['platform'])->patchJson('/api/v1/platform/tenants/'.$first['tenant']->id.'/memberships/'.$membership->id, ['status' => 'active'])->assertUnprocessable();
    $invitation = $this->actingAs($first['platform'])->postJson('/api/v1/platform/tenants/'.$first['tenant']->id.'/administrators/invitations', ['email' => 'cross@example.test', 'company_id' => $first['company']->id, 'role_id' => $first['role']->id])->assertCreated()->json('data.id');
    $this->actingAs($first['platform'])->postJson('/api/v1/platform/tenants/'.$second['tenant']->id.'/administrators/invitations/'.$invitation.'/resend')->assertNotFound();
    $this->actingAs($first['platform'])->postJson('/api/v1/platform/tenants/'.$second['tenant']->id.'/administrators/invitations/'.$invitation.'/revoke')->assertNotFound();
});

it('lists authorized business administrators globally with business scope', function (): void {
    $f = platformBusinessFixture();
    $response = $this->actingAs($f['platform'])->getJson('/api/v1/platform/business-admins')->assertOk();
    $response->assertJsonStructure(['data' => ['data' => [['business' => ['id', 'name', 'slug', 'status'], 'user', 'roles']]]]);
    expect($response->json('data.total'))->toBe(1);
    $this->actingAs($f['owner'])->getJson('/api/v1/platform/business-admins')->assertForbidden();
});

it('exports only authorized business directory fields', function (): void {
    $f = platformBusinessFixture();
    $this->actingAs($f['platform'])->get('/api/v1/platform/tenants/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $this->actingAs($f['owner'])->get('/api/v1/platform/tenants/export')->assertForbidden();
});
