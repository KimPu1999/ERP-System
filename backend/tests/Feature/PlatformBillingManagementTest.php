<?php

use App\Models\{PlatformAuditLog, PlatformInvoice, PlatformPayment, SubscriptionPlan, Tenant, TenantSubscription, User};
use Illuminate\Support\Facades\Hash;

function billingPlatformUser(): User
{
    return User::factory()->create(['is_platform_super_admin' => true]);
}

function billingPlan(array $overrides = []): SubscriptionPlan
{
    return SubscriptionPlan::create(array_merge(['code' => 'pro-' . fake()->unique()->numerify('###'), 'name' => 'Pro', 'currency_code' => 'USD', 'monthly_price' => '29.990000', 'annual_price' => '299.000000', 'trial_days' => 14, 'is_active' => true], $overrides));
}

it('keeps platform billing endpoints restricted to verified platform administrators', function (): void {
    $business = User::factory()->create(['is_platform_super_admin' => false]);
    $this->actingAs($business)->getJson('/api/v1/platform/billing/plans')->assertForbidden();
    $this->actingAs(billingPlatformUser())->getJson('/api/v1/platform/billing/plans')->assertOk();
});

it('creates auditable plans with validated feature definitions', function (): void {
    $platform = billingPlatformUser();
    $response = $this->actingAs($platform)->postJson('/api/v1/platform/billing/plans', [
        'code' => 'growth', 'name' => 'Growth', 'currency_code' => 'usd', 'monthly_price' => '49.99', 'annual_price' => '499.00', 'trial_days' => 21,
        'features' => [['key' => 'companies', 'value' => 5, 'type' => 'integer'], ['key' => 'module.pos', 'value' => true, 'type' => 'boolean']],
    ])->assertCreated()->assertJsonPath('data.code', 'growth');
    expect($response->json('data.features'))->toHaveCount(2)
        ->and(PlatformAuditLog::where('action', 'platform.subscription_plan.created')->exists())->toBeTrue();
    $this->actingAs($platform)->postJson('/api/v1/platform/billing/plans', ['code' => 'growth', 'name' => 'Duplicate', 'currency_code' => 'USD', 'monthly_price' => 1, 'annual_price' => 10, 'trial_days' => 0])->assertUnprocessable();
});

it('rejects commercial subscription assignment to a pending business and snapshots price on active assignment', function (): void {
    $platform = billingPlatformUser();
    $plan = billingPlan(['monthly_price' => '80.000000']);
    $pending = Tenant::factory()->create(['status' => 'pending']);
    TenantSubscription::create(['tenant_id' => $pending->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'state' => 'trialing', 'billing_cycle' => 'monthly']);
    $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/' . $pending->id . '/subscription', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertUnprocessable();

    $tenant = Tenant::factory()->create(['status' => 'active']);
    $old = billingPlan(['code' => 'old-' . fake()->unique()->numerify('###'), 'monthly_price' => '10.000000']);
    TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $old->id, 'provider' => 'manual', 'state' => 'trialing', 'billing_cycle' => 'monthly']);
    $response = $this->actingAs($platform)->patchJson('/api/v1/platform/tenants/' . $tenant->id . '/subscription', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertOk();
    expect($response->json('data.price_snapshot'))->toBe('80.000000')
        ->and(TenantSubscription::where('tenant_id', $tenant->id)->value('currency_code'))->toBe('USD');
});

it('issues an invoice and reconciles only a verified payment', function (): void {
    $platform = billingPlatformUser(); $plan = billingPlan(['monthly_price' => '25.000000']); $tenant = Tenant::factory()->create(['status' => 'active']);
    $subscription = TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'state' => 'active', 'billing_cycle' => 'monthly', 'price_snapshot' => '25.000000', 'currency_code' => 'USD', 'seat_count' => 1]);
    $invoiceResponse = $this->actingAs($platform)->postJson('/api/v1/platform/billing/invoices', ['tenant_id' => $tenant->id])->assertCreated();
    $invoiceId = $invoiceResponse->json('data.id');
    expect($this->actingAs($platform)->postJson('/api/v1/platform/billing/invoices', ['tenant_id' => $tenant->id])->assertCreated()->json('data.id'))->toBe($invoiceId);
    $paymentResponse = $this->actingAs($platform)->postJson('/api/v1/platform/billing/payments', ['invoice_id' => $invoiceId, 'amount' => '25.000000', 'currency_code' => 'USD'])->assertCreated()->assertJsonPath('data.status', 'pending');
    $paymentId = $paymentResponse->json('data.id');
    expect(PlatformInvoice::find($invoiceId)->paid_total)->toBe('0.000000');
    $this->actingAs($platform)->postJson('/api/v1/platform/billing/payments/' . $paymentId . '/verify')->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect(PlatformInvoice::find($invoiceId)->fresh()->status)->toBe('paid')
        ->and(PlatformAuditLog::where('action', 'platform.payment.verified')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('blocks mismatched currency and overpayment before creating a payment', function (): void {
    $platform = billingPlatformUser(); $plan = billingPlan(['monthly_price' => '10.000000']); $tenant = Tenant::factory()->create(['status' => 'active']);
    $subscription = TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'provider' => 'manual', 'state' => 'active', 'billing_cycle' => 'monthly', 'price_snapshot' => '10.000000', 'currency_code' => 'USD']);
    $invoice = PlatformInvoice::create(['tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'number' => 'TEST-' . fake()->unique()->numerify('###'), 'status' => 'open', 'currency_code' => 'USD', 'subtotal' => '10', 'tax_total' => '0', 'total' => '10', 'paid_total' => '0', 'issued_on' => now(), 'due_on' => now()->addDay()]);
    $this->actingAs($platform)->postJson('/api/v1/platform/billing/payments', ['invoice_id' => $invoice->id, 'amount' => '1', 'currency_code' => 'MMK'])->assertUnprocessable();
    $this->actingAs($platform)->postJson('/api/v1/platform/billing/payments', ['invoice_id' => $invoice->id, 'amount' => '11', 'currency_code' => 'USD'])->assertUnprocessable();
    expect(PlatformPayment::where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('verifies signed webhooks once and rejects unsigned or incomplete events', function (): void {
    config(['services.billing.webhook_secrets.default' => 'test-secret']);
    $plan = billingPlan(); $tenant = Tenant::factory()->create(['status' => 'active']);
    TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'provider' => 'default', 'provider_subscription_id' => 'sub-1', 'state' => 'trialing', 'billing_cycle' => 'monthly']);
    $payload = ['id' => 'evt-1', 'type' => 'subscription.updated', 'data' => ['subscription_id' => 'sub-1', 'state' => 'active']]; $raw = json_encode($payload); $signature = hash_hmac('sha256', $raw, 'test-secret');
    $this->postJson('/api/v1/billing/webhooks/default', $payload, ['X-Billing-Signature' => $signature])->assertOk()->assertJsonPath('data.state', 'processed');
    $this->postJson('/api/v1/billing/webhooks/default', $payload, ['X-Billing-Signature' => $signature])->assertOk();
    expect(TenantSubscription::where('provider_subscription_id', 'sub-1')->value('state'))->toBe('active');
    $this->postJson('/api/v1/billing/webhooks/default', ['id' => 'evt-bad'], ['X-Billing-Signature' => 'bad'])->assertUnauthorized();
    $missingId = ['type' => 'subscription.updated']; $missingRaw = json_encode($missingId);
    $this->postJson('/api/v1/billing/webhooks/default', $missingId, ['X-Billing-Signature' => hash_hmac('sha256', $missingRaw, 'test-secret')])->assertUnprocessable();
});
