<?php

use App\Models\{OnboardingProgress, PlatformAuditLog, SupportTicket, Tenant, TenantMembership, User};

function growthSuperAdmin(): User
{
    return User::factory()->create(['is_platform_super_admin' => true, 'email_verified_at' => now()]);
}

function growthTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->create($attributes);
}

it('deduplicates registration cohort metrics across setup retries', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $tenant = growthTenant(['created_at' => now()->subDays(3)]);
    // A retried owner membership and duplicate completed step writes must not inflate cohort counts:
    // the (tenant_id, step_key) unique contract turns a retry into an update of the same record.
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'status' => 'active']);
    OnboardingProgress::create(['tenant_id' => $tenant->id, 'step_key' => 'company_profile', 'status' => 'completed', 'completed_at' => now()->subDays(2)]);
    OnboardingProgress::updateOrCreate(['tenant_id' => $tenant->id, 'step_key' => 'company_profile'], ['status' => 'completed', 'completed_at' => now()->subDay()]);

    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/onboarding')->assertOk();

    expect($response->json('data.metrics.registrations'))->toBe(1)
        ->and($response->json('data.metrics.onboarding_started.value'))->toBe(1)
        // The single retry of company_profile is deduplicated to one progress row,
        // and activation is still 0% because workspace_readiness was never reached.
        ->and($response->json('data.metrics.onboarding_completed.value'))->toBe(0)
        ->and($response->json('data.metrics.activation_rate_percent.value'))->toBe(0)
        ->and($response->json('data.metrics.activation_rate_percent.available'))->toBeTrue();
});

it('renders the full onboarding funnel from authoritative records', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $tenant = growthTenant(['created_at' => now()->subDays(20), 'status' => 'active']);
    $owner = User::factory()->create(['email_verified_at' => now()]);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'status' => 'active']);
    PlatformAuditLog::create(['action' => 'platform.tenant.status_changed', 'auditable_type' => Tenant::class, 'auditable_id' => $tenant->id, 'tenant_id' => $tenant->id, 'metadata' => ['from' => 'pending', 'to' => 'active']]);
    OnboardingProgress::create(['tenant_id' => $tenant->id, 'step_key' => 'workspace_readiness', 'status' => 'completed', 'completed_at' => now()->subDays(10)]);

    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/onboarding?period=last_30_days')->assertOk();
    $metrics = $response->json('data.metrics');

    expect($metrics['registrations'])->toBe(1)
        ->and($metrics['verified_registrations']['value'])->toBe(1)
        ->and($metrics['approved_businesses']['value'])->toBe(1)
        ->and($metrics['onboarding_completed']['value'])->toBe(1)
        ->and($metrics['activation_rate_percent']['value'])->toBe(100)
        ->and($response->json('data.trend.0.activations'))->toBeGreaterThanOrEqual(0);
});

it('blocks the growth endpoints for non platform administrators', function (): void {
    config(['security.platform_mfa_required' => false]);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $tenant = growthTenant();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $member->id, 'status' => 'active']);
    $this->actingAs($member)->getJson('/api/v1/platform/growth/onboarding')->assertForbidden();
    $this->actingAs($member)->getJson('/api/v1/platform/growth/customer-success')->assertForbidden();
});

it('validates growth filters server-side', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $this->actingAs($admin)->getJson('/api/v1/platform/growth/onboarding?granularity=yearly2')->assertUnprocessable();
    $this->actingAs($admin)->getJson('/api/v1/platform/growth/onboarding?plan_id=not-a-uuid')->assertUnprocessable();
});

it('treats module enabled as configuration, not usage, and reports workflow states', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $tenant = growthTenant();
    $tenant->modules()->create(['module_key' => 'pos', 'enabled' => true]);

    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/adoption')->assertOk();
    $enabled = collect($response->json('data.enabled_modules.data'))->firstWhere('module_key', 'pos');

    expect($enabled['businesses_enabled'])->toBe(1)
        ->and($response->json('data.enabled_modules.definition'))->toContain('explicitly not module usage')
        ->and($response->json('data.module_opened_events.available'))->toBeFalse();
});

it('surfaces facts-only customer success rows for attention follow-up', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $tenant = growthTenant(['status' => 'active']);
    SupportTicket::create(['tenant_id' => $tenant->id, 'created_by' => $admin->id, 'subject' => 'Blocked setup', 'description' => 'Cannot finish catalog', 'priority' => 'high', 'status' => 'open', 'ticket_number' => 'TKN-GROWTH-0001', 'category' => 'general']);

    // No workspace_readiness completion exists, so the active but un-activated tenant
    // belongs in the attention queue; rows are paginated (data.data is the paginator).
    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/customer-success')->assertOk();
    expect($response->json('data.data.data.0.id'))->toBeString()
        ->and($response->json('data.data.data.0.follow_up.0'))->toBe('onboarding')
        ->and($response->json('data.data.data.0.open_support_tickets'))->toBe(1)
        ->and($response->json('data.summary.attention_counts.open_support_tickets'))->toBe(1)
        ->and($response->json('data.signals_documentation'))->toContain('No heuristic health score');
});

it('reports churn events without fabricating percentages', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $tenant = growthTenant();
    PlatformAuditLog::create(['action' => 'platform.tenant.subscription_canceled', 'auditable_type' => TenantSubscription::class, 'auditable_id' => (string) Illuminate\Support\Str::uuid(), 'tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/retention')->assertOk();

    expect($response->json('data.events.cancellations_in_window.value'))->toBe(1)
        ->and($response->json('data.customer_churn_rate_percent.available'))->toBeFalse()
        ->and($response->json('data.trial_conversion.available'))->toBeFalse()
        ->and($response->json('data.revenue_retention.available'))->toBeFalse();
});

it('falls back to current-state cohort retention clearly labeled', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    growthTenant(['status' => 'active', 'created_at' => now()->subMonths(2)]);
    growthTenant(['status' => 'rejected', 'created_at' => now()->subMonth()]);

    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/retention')->assertOk();
    $cohorts = collect($response->json('data.cohorts_current_state.cohorts'));

    expect($response->json('data.cohorts_current_state.definition'))->toContain('current-state snapshot')
        ->and($cohorts->count())->toBe(2);
});

it('does not expose another tenant lifecycle signal inside growth aggregates', function (): void {
    $admin = growthSuperAdmin();
    config(['security.platform_mfa_required' => false]);
    $inside = growthTenant(['created_at' => now()->subDays(2)]);
    $outside = growthTenant(['created_at' => now()->subDays(90)]);
    OnboardingProgress::create(['tenant_id' => $outside->id, 'step_key' => 'workspace_readiness', 'status' => 'completed', 'completed_at' => now()->subDays(80)]);

    $response = $this->actingAs($admin)->getJson('/api/v1/platform/growth/onboarding?period=last_30_days')->assertOk();

    expect($response->json('data.metrics.onboarding_completed.value'))->toBe(0)
        ->and($response->json('data.metrics.registrations'))->toBe(1);
});
