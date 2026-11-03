<?php

namespace App\Services;

use App\Models\{BillingWebhookEvent, PlatformInvoice, PlatformPayment, SubscriptionPlan, Tenant, TenantSubscription};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Closure;

final class SubscriptionService
{
    public function provisionTrial(Tenant $tenant): TenantSubscription
    {
        return DB::transaction(function () use ($tenant): TenantSubscription {
            $existing = TenantSubscription::where('tenant_id', $tenant->id)->lockForUpdate()->first();
            if ($existing) return $existing->load('plan.features');
            $subscriptionSettings = app(SettingsService::class)->getForPolicy('subscriptions');
            $plan = filled($subscriptionSettings['default_plan_id'] ?? null)
                ? SubscriptionPlan::where('is_active', true)->whereNull('archived_at')->find($subscriptionSettings['default_plan_id'])
                : null;
            $plan ??= SubscriptionPlan::where('is_active', true)->whereNull('archived_at')->orderBy('monthly_price')->first();
            if (! $plan) {
                $plan = SubscriptionPlan::create([
                    'code' => 'starter', 'name' => 'Starter Trial', 'description' => 'Default trial plan; configure before commercial use.',
                    'currency_code' => 'USD', 'monthly_price' => '0', 'annual_price' => '0',
                    'trial_days' => (int) ((bool) ($subscriptionSettings['trial_eligible'] ?? true) ? ($subscriptionSettings['trial_duration_days'] ?? config('services.billing.trial_days', 14)) : 0), 'is_active' => true,
                ]);
            }
            $now = now(); $trialDays = (bool) ($subscriptionSettings['trial_eligible'] ?? true) ? (int) ($subscriptionSettings['trial_duration_days'] ?? $plan->trial_days) : 0;
            return TenantSubscription::create([
                'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
                'state' => $trialDays > 0 ? 'trialing' : 'active', 'trial_ends_at' => $trialDays > 0 ? $now->copy()->addDays($trialDays) : null,
                'current_period_starts_at' => $now, 'current_period_ends_at' => $now->copy()->addMonth(),
                'provider' => config('services.billing.provider', 'manual'), 'seat_count' => 1,
                'price_snapshot' => $plan->monthly_price, 'currency_code' => $plan->currency_code,
            ])->load('plan.features');
        });
    }

    public function active(Tenant $tenant): TenantSubscription
    {
        abort_unless($tenant->status === 'active', 403, 'The business workspace is not active.');
        $subscription = $this->syncState($tenant->id);
        if (! in_array($subscription->state, ['trialing', 'active', 'past_due'], true) || ($subscription->state === 'past_due' && $subscription->grace_ends_at?->isPast())) {
            throw ValidationException::withMessages(['subscription' => ['The tenant subscription does not permit this operation.']]);
        }
        return $subscription;
    }

    public function syncState(string $tenantId): TenantSubscription
    {
        return DB::transaction(function () use ($tenantId): TenantSubscription {
            return $this->synchronizeLocked(TenantSubscription::where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail());
        });
    }

    public function limit(string $tenantId, string $feature, int $current): void
    {
        $subscription = $this->syncState($tenantId);
        if (! in_array($subscription->state, ['trialing', 'active', 'past_due'], true) || ($subscription->state === 'past_due' && $subscription->grace_ends_at?->isPast())) throw ValidationException::withMessages(['subscription' => ['The subscription does not permit this operation.']]);
        $entitlement = $subscription->plan->features->firstWhere('feature_key', $feature);
        if (! $entitlement) return;
        if ($entitlement->value_type === 'integer' && $current >= (int) $entitlement->value) throw ValidationException::withMessages(['entitlement' => ["The plan limit for {$feature} has been reached."]]);
        if ($entitlement->value_type === 'boolean' && ! filter_var($entitlement->value, FILTER_VALIDATE_BOOLEAN)) throw ValidationException::withMessages(['entitlement' => ["The {$feature} entitlement is not included in this plan."]]);
    }

    /**
     * Lock the subscription for the complete create operation so concurrent
     * requests cannot both pass a tenant limit before inserting their rows.
     */
    public function withinLimit(string $tenantId, string $feature, int|Closure $current, Closure $operation): mixed
    {
        return DB::transaction(function () use ($tenantId, $feature, $current, $operation): mixed {
            $subscription = TenantSubscription::where('tenant_id', $tenantId)->lockForUpdate()->first();
            // Some legacy workspaces predate subscription provisioning. Keep
            // their existing business operations available; limits only apply
            // once a subscription has been explicitly provisioned.
            if (! $subscription) return $operation();
            $subscription = $this->synchronizeLocked($subscription);
            $currentCount = $current instanceof Closure ? (int) $current() : $current;
            $entitlement = $subscription->plan->features->firstWhere('feature_key', $feature);
            if (! $entitlement) return $operation();
            if ($entitlement->value_type === 'integer' && $currentCount >= (int) $entitlement->value) throw ValidationException::withMessages(['entitlement' => ["The plan limit for {$feature} has been reached."]]);
            if ($entitlement->value_type === 'boolean' && ! filter_var($entitlement->value, FILTER_VALIDATE_BOOLEAN)) throw ValidationException::withMessages(['entitlement' => ["The {$feature} entitlement is not included in this plan."]]);
            return $operation();
        }, 3);
    }

    private function synchronizeLocked(TenantSubscription $subscription): TenantSubscription
    {
        $now = now();
        $changes = [];
        if ($subscription->state === 'trialing' && $subscription->trial_ends_at?->isPast()) $changes = ['state' => 'past_due', 'grace_ends_at' => $now->copy()->addDays((int) config('services.billing.grace_days', 7))];
        elseif ($subscription->state === 'active' && $subscription->current_period_ends_at?->isPast()) $changes = ['state' => 'past_due', 'grace_ends_at' => $now->copy()->addDays((int) config('services.billing.grace_days', 7))];
        elseif ($subscription->state === 'past_due' && $subscription->grace_ends_at?->isPast()) $changes = ['state' => 'suspended'];
        if ($changes) $subscription->update($changes);
        return $subscription->fresh('plan.features');
    }

    public function changePlan(string $tenantId, string $planId, string $cycle): TenantSubscription
    {
        return DB::transaction(function () use ($tenantId, $planId, $cycle): TenantSubscription {
            $subscription = TenantSubscription::where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail(); $tenant = Tenant::findOrFail($tenantId);
            abort_unless($tenant->status === 'active', 422, 'Only active businesses can receive a commercial subscription.');
            if (! in_array($cycle, ['monthly', 'annual'], true)) throw ValidationException::withMessages(['billing_cycle' => ['Unsupported billing cycle.']]);
            $plan = SubscriptionPlan::where('is_active', true)->whereNull('archived_at')->findOrFail($planId); $now = now();
            $subscription->update(['plan_id' => $plan->id, 'billing_cycle' => $cycle, 'state' => 'active', 'trial_ends_at' => null, 'current_period_starts_at' => $now, 'current_period_ends_at' => $now->copy()->addMonths($cycle === 'annual' ? 12 : 1), 'grace_ends_at' => null, 'canceled_at' => null, 'price_snapshot' => $cycle === 'annual' ? $plan->annual_price : $plan->monthly_price, 'currency_code' => $plan->currency_code]);
            return $subscription->fresh('plan.features');
        });
    }

    public function cancel(string $tenantId): TenantSubscription
    {
        return DB::transaction(function () use ($tenantId): TenantSubscription { $subscription = TenantSubscription::where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail(); if ($subscription->state !== 'canceled') $subscription->update(['state' => 'canceled', 'canceled_at' => now()]); return $subscription->fresh('plan.features'); });
    }

    public function renew(string $tenantId): TenantSubscription
    {
        return DB::transaction(function () use ($tenantId): TenantSubscription {
            $subscription = TenantSubscription::where('tenant_id', $tenantId)->with('plan')->lockForUpdate()->firstOrFail(); abort_unless(Tenant::findOrFail($tenantId)->status === 'active', 422, 'Only active businesses can renew a subscription.'); $now = now();
            $subscription->update(['state' => 'active', 'canceled_at' => null, 'grace_ends_at' => null, 'current_period_starts_at' => $now, 'current_period_ends_at' => $now->copy()->addMonths($subscription->billing_cycle === 'annual' ? 12 : 1), 'price_snapshot' => $subscription->billing_cycle === 'annual' ? $subscription->plan->annual_price : $subscription->plan->monthly_price, 'currency_code' => $subscription->plan->currency_code]);
            return $subscription->fresh('plan.features');
        });
    }

    public function webhook(string $provider, string $raw, string $signature, array $payload): BillingWebhookEvent
    {
        $secret = config('services.billing.webhook_secrets.' . $provider); abort_unless($secret, 503, 'Billing provider is not configured.');
        $signature = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature; abort_unless($signature !== '' && hash_equals(hash_hmac('sha256', $raw, $secret), $signature), 401, 'Invalid billing webhook signature.');
        $eventId = trim((string) ($payload['id'] ?? '')); abort_unless($eventId !== '', 422, 'The billing webhook event id is required.');
        return DB::transaction(function () use ($provider, $payload, $eventId): BillingWebhookEvent {
            $event = BillingWebhookEvent::where('provider', $provider)->where('provider_event_id', $eventId)->lockForUpdate()->first(); if ($event?->state === 'processed') return $event;
            $event ??= BillingWebhookEvent::create(['provider' => $provider, 'provider_event_id' => $eventId, 'event_type' => (string) ($payload['type'] ?? 'unknown'), 'payload' => $payload, 'signature_verified' => true, 'state' => 'received']);
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : []; $external = (string) ($data['subscription_id'] ?? '');
            if ($external) { $subscription = TenantSubscription::where('provider', $provider)->where('provider_subscription_id', $external)->lockForUpdate()->first(); $state = (string) ($data['state'] ?? ''); if ($subscription && in_array($state, ['trialing', 'active', 'past_due', 'suspended', 'canceled'], true)) $subscription->update(['state' => $state]); }
            $paymentExternal = (string) ($data['payment_id'] ?? ''); $invoiceId = (string) ($data['invoice_id'] ?? ''); $paymentState = (string) ($data['payment_state'] ?? $data['status'] ?? '');
            if ($paymentExternal && $invoiceId && in_array($paymentState, ['succeeded', 'paid', 'settled'], true)) {
                $invoice = PlatformInvoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail(); $amount = (string) ($data['amount'] ?? '0'); $currency = strtoupper((string) ($data['currency_code'] ?? ''));
                abort_unless($currency !== '' && $currency === strtoupper((string) $invoice->currency_code), 422, 'Webhook payment currency does not match the invoice.');
                abort_if(bccomp($amount, bcsub((string) $invoice->total, (string) $invoice->paid_total, 6), 6) > 0, 422, 'Webhook payment exceeds the outstanding invoice balance.');
                $providerPaymentId = $provider . ':' . $paymentExternal; $payment = PlatformPayment::where('provider_payment_id', $providerPaymentId)->lockForUpdate()->first();
                if (! $payment) { PlatformPayment::create(['tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'provider' => $provider, 'provider_payment_id' => $providerPaymentId, 'status' => 'succeeded', 'amount' => $amount, 'currency_code' => $currency, 'verified_at' => now(), 'provider_metadata' => ['webhook_event_id' => $eventId]]); $paid = bcadd((string) $invoice->paid_total, $amount, 6); $invoice->update(['paid_total' => $paid, 'status' => bccomp($paid, (string) $invoice->total, 6) >= 0 ? 'paid' : 'open']); }
            }
            $event->update(['payload' => $payload, 'state' => 'processed', 'processed_at' => now(), 'error' => null]); return $event->fresh();
        });
    }
}
