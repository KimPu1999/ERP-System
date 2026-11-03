<?php

use App\Models\{Branch, Company, Permission, PlatformInvoice, Product, ProductCategory, Role, RoleAssignment, SubscriptionPlan, Tenant, TenantMembership, TenantSubscription, UnitOfMeasure, User};
use App\Services\OnboardingProgressService;

function onboardingAdminFixture(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $tenant = Tenant::factory()->create(['status' => 'active']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'organization.manage'], ['description' => 'Manage organization']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Onboarding Admin', 'slug' => 'onboarding-admin']);
    $role->permissions()->attach($permission);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    return [$user, $tenant];
}

it('reports authoritative onboarding progress and rejects spoofed completion', function (): void {
    [$user, $tenant] = onboardingAdminFixture();
    $response = $this->actingAs($user)->getJson('/api/v1/business/onboarding', ['X-Tenant-Id' => $tenant->id])->assertOk();
    expect($response->json('data.progress_percent'))->toBe(22);
    $this->actingAs($user)->postJson('/api/v1/business/onboarding/company_profile/complete', [], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
});

it('does not expose onboarding progress across tenant boundaries', function (): void {
    [$user, $tenant] = onboardingAdminFixture();
    $other = Tenant::factory()->create(['status' => 'active']);
    $this->actingAs($user)->getJson('/api/v1/business/onboarding', ['X-Tenant-Id' => $other->id])->assertForbidden();
    expect(app(OnboardingProgressService::class)->overview($tenant, $user->id)['tenant_id'])->toBe($tenant->id);
});

it('records a completed step idempotently and recognizes live setup data', function (): void {
    [$user, $tenant] = onboardingAdminFixture();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Acme', 'code' => 'ACME', 'is_active' => true, 'base_currency' => 'MMK', 'fiscal_year_start' => '2026-04-01']);
    Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    UnitOfMeasure::create(['tenant_id' => $tenant->id, 'name' => 'Each', 'code' => 'EA', 'decimal_places' => 0]);
    $category = ProductCategory::create(['tenant_id' => $tenant->id, 'name' => 'General', 'slug' => 'general']);
    Product::create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Widget', 'slug' => 'widget', 'status' => 'active']);
    $headers = ['X-Tenant-Id' => $tenant->id];
    $this->actingAs($user)->postJson('/api/v1/business/onboarding/company_profile/complete', [], $headers)->assertOk()->assertJsonPath('data.steps.2.source_type', 'companies')->assertJsonPath('data.steps.2.source_id', $company->id);
    $this->postJson('/api/v1/business/onboarding/company_profile/complete', [], $headers)->assertOk();
    expect($tenant->fresh()->id)->toBe($tenant->id)->and(DB::table('onboarding_progress')->where('tenant_id', $tenant->id)->where('step_key', 'company_profile')->count())->toBe(1);
});

it('exposes self-service usage only inside the resolved tenant', function (): void {
    [$user, $tenant] = onboardingAdminFixture();
    $this->actingAs($user)->getJson('/api/v1/business/self-service', ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonStructure(['data' => ['account', 'business', 'usage', 'entitlements', 'modules', 'team']]);
    $other = Tenant::factory()->create(['status' => 'active']);
    $this->getJson('/api/v1/business/usage', ['X-Tenant-Id' => $other->id])->assertForbidden();
});

it('restricts self-service billing and team data to authorized tenant admins', function (): void {
    [$user, $tenant] = onboardingAdminFixture();
    $plan = SubscriptionPlan::create(['code' => 'self-service-test', 'name' => 'Self Service Test', 'currency_code' => 'USD', 'monthly_price' => '10', 'annual_price' => '100', 'is_active' => true]);
    $subscription = TenantSubscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'state' => 'active',
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'provider' => 'manual',
        'seat_count' => 1,
        'price_snapshot' => '10',
        'currency_code' => 'USD',
    ]);
    $invoice = PlatformInvoice::create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'number' => 'INV-SELF-001',
        'status' => 'open',
        'currency_code' => 'USD',
        'subtotal' => '10',
        'tax_total' => '0',
        'total' => '10',
        'paid_total' => '0',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(14)->toDateString(),
    ]);
    $other = Tenant::factory()->create(['status' => 'active']);
    $otherPlan = SubscriptionPlan::create(['code' => 'other-tenant-test', 'name' => 'Other Tenant Test', 'currency_code' => 'USD', 'monthly_price' => '10', 'annual_price' => '100', 'is_active' => true]);
    $otherSubscription = TenantSubscription::create(['tenant_id' => $other->id, 'plan_id' => $otherPlan->id, 'billing_cycle' => 'monthly', 'state' => 'active', 'current_period_starts_at' => now(), 'current_period_ends_at' => now()->addMonth(), 'provider' => 'manual', 'seat_count' => 1, 'price_snapshot' => '10', 'currency_code' => 'USD']);
    $otherInvoice = PlatformInvoice::create(['tenant_id' => $other->id, 'subscription_id' => $otherSubscription->id, 'number' => 'INV-OTHER-001', 'status' => 'open', 'currency_code' => 'USD', 'subtotal' => '10', 'tax_total' => '0', 'total' => '10', 'paid_total' => '0', 'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(14)->toDateString()]);

    $headers = ['X-Tenant-Id' => $tenant->id];
    $this->actingAs($user)->getJson('/api/v1/business/billing', $headers)->assertOk()->assertJsonPath('data.invoices.data.0.id', $invoice->id)->assertJsonMissing(['id' => $otherInvoice->id]);
    $this->getJson('/api/v1/business/billing/invoices/'.$otherInvoice->id, $headers)->assertNotFound();
    $this->getJson('/api/v1/business/team', $headers)->assertOk()->assertJsonPath('data.data.0.email', $user->email);

    $newName = 'Updated Owner Name';
    $this->patchJson('/api/v1/business/account', ['name' => $newName, 'email' => 'spoof@example.test'], $headers)->assertOk()->assertJsonPath('data.name', $newName)->assertJsonPath('data.email', $user->email);
});

it('does not expose self-service team or billing records to a regular member', function (): void {
    [$admin, $tenant] = onboardingAdminFixture();
    $member = User::factory()->create(['email_verified_at' => now()]);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $member->id, 'status' => 'active']);

    $headers = ['X-Tenant-Id' => $tenant->id];
    $this->actingAs($member)->getJson('/api/v1/business/team', $headers)->assertForbidden();
    $this->getJson('/api/v1/business/billing', $headers)->assertForbidden();
    expect($admin->id)->not->toBe($member->id);
});

it('installs tenant RLS policies for Phase 13 records', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL RLS is only available on PostgreSQL.');
    }

    $tables = DB::table('pg_policies')->whereIn('tablename', ['onboarding_progress', 'platform_invoice_items'])->pluck('tablename')->unique()->values()->all();
    expect($tables)->toContain('onboarding_progress')->toContain('platform_invoice_items');
});
