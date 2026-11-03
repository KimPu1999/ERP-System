<?php

use Carbon\CarbonImmutable;
use App\Models\{PlatformAuditLog, PlatformInvoice, PlatformPayment, ReportExport, SubscriptionPlan, Tenant, TenantSubscription, User};
use Illuminate\Support\Facades\Queue;
use App\Jobs\GeneratePlatformReportExport;

function analyticsPlatformUser(): User
{
    return User::factory()->create(['is_platform_super_admin' => true]);
}

it('restricts the phase six analytics and reports endpoints to platform administrators', function (): void {
    $this->getJson('/api/v1/platform/analytics/overview')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/analytics/overview')->assertForbidden();
    $this->actingAs(analyticsPlatformUser())->getJson('/api/v1/platform/analytics/overview')->assertOk()->assertJsonStructure(['data' => ['kpis', 'businesses', 'subscriptions', 'revenue', 'metric_definitions']]);
});

it('uses real tenant and activity data with timezone-safe custom boundaries', function (): void {
    $platform = analyticsPlatformUser();
    $day = CarbonImmutable::now('Asia/Yangon')->subDays(3)->startOfDay();
    Tenant::factory()->create(['created_at' => $day->addHour()->setTimezone('UTC'), 'updated_at' => $day->addHour()->setTimezone('UTC'), 'status' => 'active']);
    Tenant::factory()->create(['created_at' => $day->subDay()->addHour()->setTimezone('UTC'), 'updated_at' => $day->subDay()->addHour()->setTimezone('UTC'), 'status' => 'pending']);
    $response = $this->actingAs($platform)->getJson('/api/v1/platform/analytics/businesses?period=custom&granularity=daily&from='.$day->format('Y-m-d').'&to='.$day->format('Y-m-d'))->assertOk();
    expect($response->json('data.filters.timezone'))->toBe('Asia/Yangon')
        ->and($response->json('data.summary.registered'))->toBe(1)
        ->and($response->json('data.trend.0.count'))->toBe(1);
});

it('keeps billing analytics exact, verified-only, and separated by currency', function (): void {
    $platform = analyticsPlatformUser();
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $plan = SubscriptionPlan::create(['code' => 'analytics-'.fake()->unique()->numerify('###'), 'name' => 'Analytics', 'currency_code' => 'USD', 'monthly_price' => '10.000000', 'annual_price' => '100.000000', 'trial_days' => 0, 'is_active' => true]);
    $subscription = TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'state' => 'active', 'billing_cycle' => 'monthly', 'price_snapshot' => '10.000000', 'currency_code' => 'USD']);
    $invoice = PlatformInvoice::create(['tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'number' => 'AN-001', 'status' => 'open', 'currency_code' => 'USD', 'subtotal' => '10', 'tax_total' => '0', 'total' => '10', 'paid_total' => '2', 'issued_on' => now(), 'due_on' => now()->addDay()]);
    PlatformPayment::create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'provider' => 'manual', 'provider_payment_id' => 'pending', 'status' => 'pending', 'amount' => '8', 'currency_code' => 'USD']);
    PlatformPayment::create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'provider' => 'manual', 'provider_payment_id' => 'verified', 'status' => 'succeeded', 'amount' => '2', 'currency_code' => 'USD', 'verified_at' => now()]);
    $response = $this->actingAs($platform)->getJson('/api/v1/platform/analytics/revenue')->assertOk();
    expect($response->json('data.totals.currencies.0.collected'))->toBe('2.000000')
        ->and($response->json('data.totals.currencies.0.outstanding'))->toBe('8.000000')
        ->and($response->json('data.refunds.available'))->toBeFalse();
    $financial = $this->actingAs($platform)->getJson('/api/v1/platform/reports/financial?report=invoices')->assertOk();
    expect($financial->json('data.data.data.0.outstanding'))->toBe('8.000000');
});

it('queues private platform CSV exports and audits the request', function (): void {
    Queue::fake();
    $platform = analyticsPlatformUser();
    $response = $this->actingAs($platform)->postJson('/api/v1/platform/reports/exports', ['report_key' => 'invoices', 'format' => 'csv', 'filters' => ['period' => 'monthly']])->assertStatus(202);
    $export = ReportExport::findOrFail($response->json('data.id'));
    expect($export->tenant_id)->toBeNull()->and($export->platform_scope)->toBeTrue();
    Queue::assertPushed(GeneratePlatformReportExport::class);
    expect(PlatformAuditLog::where('action', 'platform.report_export.requested')->where('auditable_id', $export->id)->exists())->toBeTrue();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/reports/exports')->assertForbidden();
});
