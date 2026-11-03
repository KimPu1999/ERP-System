<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{PlatformInvoice, PlatformInvoiceItem, PlatformPayment, SubscriptionPlan, Tenant, TenantSubscription};
use App\Services\{AuditLogger, SubscriptionService};
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PlatformBillingController
{
    public function plans(Request $request)
    {
        $query = SubscriptionPlan::with('features')->when($request->boolean('include_archived'), fn ($q) => $q, fn ($q) => $q->where('is_active', true)->whereNull('archived_at'));
        if ($request->filled('search')) $query->where(fn ($q) => $q->where('name', 'ilike', '%' . $request->string('search') . '%')->orWhere('code', 'ilike', '%' . $request->string('search') . '%'));
        return response()->json(['data' => $query->orderBy('monthly_price')->get(), 'meta' => ['total' => $query->count()]]);
    }

    public function showPlan(SubscriptionPlan $plan) { return response()->json(['data' => $plan->load('features')]); }

    public function storePlan(Request $request, AuditLogger $audit)
    {
        $data = $this->planData($request);
        try {
            $plan = DB::transaction(function () use ($data): SubscriptionPlan { $plan = SubscriptionPlan::create(collect($data)->except('features')->all()); $this->syncFeatures($plan, $data['features'] ?? []); return $plan->load('features'); });
        } catch (QueryException $e) { if ((string) $e->getCode() === '23505') abort(422, 'The plan code is already in use.'); throw $e; }
        $audit->record($request, 'platform.subscription_plan.created', null, $plan, ['feature_count' => count($data['features'] ?? [])]);
        return response()->json(['data' => $plan], 201);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan, AuditLogger $audit)
    {
        abort_if($plan->archived_at, 422, 'An archived plan cannot be edited.');
        $data = $this->planData($request, $plan->id);
        $updated = DB::transaction(function () use ($data, $plan): SubscriptionPlan { $plan->update(collect($data)->except('features')->all()); if (array_key_exists('features', $data)) $this->syncFeatures($plan, $data['features']); return $plan->fresh('features'); });
        $audit->record($request, 'platform.subscription_plan.updated', null, $updated, ['feature_count' => $updated->features->count()]);
        return response()->json(['data' => $updated]);
    }

    public function archivePlan(Request $request, SubscriptionPlan $plan, AuditLogger $audit)
    {
        abort_if($plan->subscriptions()->whereIn('state', ['trialing', 'active', 'past_due'])->exists(), 422, 'A plan assigned to an active subscription cannot be archived.');
        $plan->update(['is_active' => false, 'archived_at' => now()]);
        $audit->record($request, 'platform.subscription_plan.archived', null, $plan);
        return response()->json(['data' => $plan->fresh('features')]);
    }

    public function subscriptions(Request $request)
    {
        $query = TenantSubscription::with(['plan', 'tenant:id,name,slug,status'])->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))->when($request->filled('billing_cycle'), fn ($q) => $q->where('billing_cycle', $request->string('billing_cycle')))->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->string('tenant_id')));
        if ($request->filled('search')) $query->whereHas('tenant', fn ($q) => $q->where('name', 'ilike', '%' . $request->string('search') . '%'));
        return response()->json(['data' => $query->latest()->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function showSubscription(TenantSubscription $subscription) { return response()->json(['data' => $subscription->load(['plan.features', 'tenant', 'invoices.payments'])]); }

    public function changeTenantSubscription(Request $request, Tenant $tenant, SubscriptionService $billing, AuditLogger $audit)
    {
        abort_unless($tenant->status === 'active', 422, 'Only active businesses can receive a subscription.');
        $data = $request->validate(['plan_id' => ['required', 'uuid'], 'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])]]);
        $subscription = $billing->changePlan($tenant->id, $data['plan_id'], $data['billing_cycle']);
        $audit->record($request, 'platform.tenant.subscription_changed', $tenant->id, $subscription, ['plan_id' => $data['plan_id'], 'billing_cycle' => $data['billing_cycle']]);
        return response()->json(['data' => $subscription->load('plan')]);
    }

    public function cancelTenantSubscription(Request $request, Tenant $tenant, SubscriptionService $billing, AuditLogger $audit)
    { $subscription = $billing->cancel($tenant->id); $audit->record($request, 'platform.tenant.subscription_canceled', $tenant->id, $subscription); return response()->json(['data' => $subscription]); }

    public function renewTenantSubscription(Request $request, Tenant $tenant, SubscriptionService $billing, AuditLogger $audit)
    { $subscription = $billing->renew($tenant->id); $audit->record($request, 'platform.tenant.subscription_renewed', $tenant->id, $subscription); return response()->json(['data' => $subscription]); }

    public function invoices(Request $request)
    {
        $query = PlatformInvoice::with(['tenant:id,name,slug', 'subscription.plan'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->string('tenant_id')))->when($request->filled('currency_code'), fn ($q) => $q->where('currency_code', strtoupper((string) $request->string('currency_code'))));
        if ($request->filled('search')) $query->where('number', 'ilike', '%' . $request->string('search') . '%');
        return response()->json(['data' => $query->latest('issued_on')->latest()->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function showInvoice(PlatformInvoice $invoice) { return response()->json(['data' => $invoice->load(['tenant', 'subscription.plan', 'items', 'payments'])]); }

    public function issueInvoice(Request $request, AuditLogger $audit)
    {
        $data = $request->validate(['tenant_id' => ['required', 'uuid', 'exists:tenants,id'], 'due_on' => ['nullable', 'date']]);
        $invoice = DB::transaction(function () use ($data): PlatformInvoice {
            $tenant = Tenant::findOrFail($data['tenant_id']); abort_unless($tenant->status === 'active', 422, 'Only active businesses can be invoiced.');
            $subscription = TenantSubscription::where('tenant_id', $tenant->id)->with('plan')->lockForUpdate()->firstOrFail();
            $periodKey = $subscription->id . ':' . ($subscription->current_period_starts_at?->toDateString() ?: now()->toDateString());
            $existing = PlatformInvoice::where('subscription_id', $subscription->id)->where('billing_period_key', $periodKey)->lockForUpdate()->first();
            if ($existing) return $existing->load('items');
            $currency = $subscription->currency_code ?: $subscription->plan->currency_code; $price = $subscription->price_snapshot ?? ($subscription->billing_cycle === 'annual' ? $subscription->plan->annual_price : $subscription->plan->monthly_price); $amount = bcmul((string) $price, (string) max(1, $subscription->seat_count), 6);
            $invoice = PlatformInvoice::create(['tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'billing_period_key' => $periodKey, 'number' => 'PLAT-' . now()->format('Ym') . '-' . strtoupper(Str::random(8)), 'status' => 'open', 'currency_code' => $currency, 'subtotal' => $amount, 'tax_total' => '0', 'total' => $amount, 'paid_total' => '0', 'issued_on' => now()->toDateString(), 'due_on' => $data['due_on'] ?? now()->addDays(14)->toDateString()]);
            PlatformInvoiceItem::create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'description' => $subscription->plan->name . ' subscription (' . $subscription->billing_cycle . ')', 'quantity' => max(1, $subscription->seat_count), 'unit_price' => $price, 'amount' => $amount, 'currency_code' => $currency, 'metadata' => ['plan_id' => $subscription->plan_id, 'price_snapshot' => (string) $price]]);
            return $invoice->load('items');
        });
        $audit->record($request, 'platform.invoice.issued', $invoice->tenant_id, $invoice);
        return response()->json(['data' => $invoice], 201);
    }

    public function payments(Request $request)
    {
        $query = PlatformPayment::with(['tenant:id,name,slug', 'invoice:id,number,total,status'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->string('tenant_id')));
        return response()->json(['data' => $query->latest()->paginate(min((int) $request->input('per_page', 50), 100))]);
    }

    public function recordPayment(Request $request, AuditLogger $audit)
    {
        $data = $request->validate(['invoice_id' => ['required', 'uuid', 'exists:platform_invoices,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'currency_code' => ['required', 'alpha', 'size:3'], 'provider_payment_id' => ['nullable', 'string', 'max:160'], 'provider_metadata' => ['nullable', 'array']]);
        $payment = DB::transaction(function () use ($data): PlatformPayment {
            $invoice = PlatformInvoice::whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail(); abort_unless(strtoupper($data['currency_code']) === strtoupper($invoice->currency_code), 422, 'Payment currency must match the invoice currency.');
            abort_if(bccomp((string) $data['amount'], bcsub((string) $invoice->total, (string) $invoice->paid_total, 6), 6) > 0, 422, 'Payment exceeds the outstanding invoice balance.');
            return PlatformPayment::create(['tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'provider' => 'manual', 'provider_payment_id' => ($data['provider_payment_id'] ?? null) ?: 'manual-' . Str::uuid(), 'status' => 'pending', 'amount' => $data['amount'], 'currency_code' => strtoupper($data['currency_code']), 'provider_metadata' => $data['provider_metadata'] ?? []]);
        });
        $audit->record($request, 'platform.payment.recorded', $payment->tenant_id, $payment);
        return response()->json(['data' => $payment], 201);
    }

    public function verifyPayment(Request $request, PlatformPayment $payment, AuditLogger $audit)
    {
        $verified = DB::transaction(function () use ($payment): PlatformPayment {
            $payment = PlatformPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail(); if ($payment->status === 'succeeded') return $payment->load('invoice'); abort_unless($payment->status === 'pending', 422, 'Only pending payments can be verified.');
            $invoice = PlatformInvoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail(); abort_if(bccomp((string) $payment->amount, bcsub((string) $invoice->total, (string) $invoice->paid_total, 6), 6) > 0, 422, 'Payment exceeds the outstanding invoice balance.');
            $payment->update(['status' => 'succeeded', 'verified_at' => now()]); $paid = bcadd((string) $invoice->paid_total, (string) $payment->amount, 6); $invoice->update(['paid_total' => $paid, 'status' => bccomp($paid, (string) $invoice->total, 6) >= 0 ? 'paid' : 'open']);
            return $payment->fresh('invoice');
        });
        $audit->record($request, 'platform.payment.verified', $verified->tenant_id, $verified);
        return response()->json(['data' => $verified]);
    }

    public function revenue()
    {
        $rows = PlatformInvoice::query()->select('currency_code')->selectRaw("SUM(CASE WHEN status IN ('open','paid','past_due') THEN total ELSE 0 END) AS invoiced")->selectRaw("SUM(paid_total) AS paid")->selectRaw("SUM(CASE WHEN status = 'past_due' THEN total - paid_total ELSE 0 END) AS past_due")->groupBy('currency_code')->orderBy('currency_code')->get()->map(fn ($row) => ['currency_code' => $row->currency_code, 'invoiced' => (string) $row->invoiced, 'paid' => (string) $row->paid, 'past_due' => (string) $row->past_due])->values();
        return response()->json(['data' => ['available' => $rows->count() <= 1, 'reason' => $rows->count() > 1 ? 'Revenue totals are separated by currency; no exchange-rate policy is configured.' : null, 'currencies' => $rows, 'invoiced' => $rows->count() === 1 ? $rows[0]['invoiced'] : null, 'paid' => $rows->count() === 1 ? $rows[0]['paid'] : null, 'past_due' => $rows->count() === 1 ? $rows[0]['past_due'] : null]]);
    }

    public function webhook(Request $request, string $provider, SubscriptionService $billing)
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR); $event = $billing->webhook($provider, $request->getContent(), (string) $request->header('X-Billing-Signature'), is_array($payload) ? $payload : []);
        return response()->json(['data' => $event->only(['provider', 'provider_event_id', 'state'])]);
    }

    private function planData(Request $request, ?string $ignore = null): array
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:40', Rule::unique('subscription_plans', 'code')->ignore($ignore)], 'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:5000'], 'currency_code' => ['required', 'alpha', 'size:3'], 'monthly_price' => ['required', 'numeric', 'min:0'], 'annual_price' => ['required', 'numeric', 'min:0'], 'trial_days' => ['required', 'integer', 'min:0', 'max:365'], 'features' => ['sometimes', 'array']]);
        $data['currency_code'] = strtoupper($data['currency_code']); $data['is_active'] = true; return $data;
    }

    private function syncFeatures(SubscriptionPlan $plan, array $features): void
    {
        $keys = [];
        foreach ($features as $feature) {
            abort_unless(is_array($feature) && preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', (string) ($feature['key'] ?? '')), 422, 'Each feature needs a valid key.');
            $type = (string) ($feature['type'] ?? 'boolean'); abort_unless(in_array($type, ['boolean', 'integer', 'string'], true), 422, 'Unsupported plan feature type.'); $value = $feature['value'] ?? false;
            if ($type === 'integer') abort_unless(filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= 0, 422, 'Integer feature limits must be non-negative.');
            if ($type === 'boolean') $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            $key = (string) $feature['key']; abort_if(in_array($key, $keys, true), 422, 'Plan feature keys must be unique.'); $keys[] = $key;
            $plan->features()->updateOrCreate(['feature_key' => $key], ['value' => (string) $value, 'value_type' => $type]);
        }
        if ($features !== []) $plan->features()->whereNotIn('feature_key', $keys)->delete();
    }
}
