<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{CompanyMembership, PlatformInvoice, PlatformPayment, Tenant, TenantMembership, TenantModule};
use App\Services\{AuditLogger, AuthorizationService, SubscriptionService};
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

final class BusinessSelfServiceController
{
    public function overview(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $tenant = Tenant::query()->with(['subscription.plan.features'])->whereKey($tenantId)->firstOrFail();
        $subscription = $tenant->subscription ? $subscriptions->syncState($tenantId) : null;
        $counts = [
            'companies' => DB::table('companies')->where('tenant_id', $tenantId)->count(),
            'active_companies' => DB::table('companies')->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'branches' => DB::table('branches')->where('tenant_id', $tenantId)->count(),
            'active_users' => DB::table('tenant_memberships')->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'warehouses' => DB::table('warehouses')->where('tenant_id', $tenantId)->where('is_active', true)->count(),
        ];
        $features = collect($subscription?->plan?->features ?? [])->mapWithKeys(fn ($feature) => [$feature->feature_key => ['value' => $feature->value, 'value_type' => $feature->value_type]])->all();
        return response()->json(['data' => [
            'account' => $request->user()->only(['id', 'name', 'email', 'email_verified_at', 'mfa_enabled_at', 'last_login_at']),
            'business' => $tenant->only(['id', 'name', 'slug', 'status', 'created_at']),
            'subscription' => array_merge($subscription?->only(['id', 'state', 'billing_cycle', 'trial_ends_at', 'current_period_ends_at', 'grace_ends_at', 'currency_code', 'price_snapshot']) ?? [], ['plan' => $subscription?->plan?->only(['id', 'code', 'name', 'description'])]),
            'usage' => $counts,
            'entitlements' => $features,
            'modules' => TenantModule::where('tenant_id', $tenantId)->where('enabled', true)->get(['module_key', 'enabled', 'settings']),
            'team' => ['active_members' => $counts['active_users'], 'company_memberships' => CompanyMembership::where('tenant_id', $tenantId)->where('status', 'active')->count()],
        ]]);
    }

    public function updateAccount(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        $user = $request->user();
        $user->update(['name' => trim($data['name'])]);
        $audit->record($request, 'business.account.updated', (string) app(TenantContext::class)->id(), $user, ['fields' => ['name']]);

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'email_verified_at', 'mfa_enabled_at', 'last_login_at'])]);
    }

    public function billing(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertBillingAccess($request, $authorization, $tenantId);
        $perPage = min(50, max(10, (int) $request->query('per_page', 20)));
        $invoices = PlatformInvoice::query()->where('tenant_id', $tenantId)->with('subscription.plan:id,code,name')->latest('issued_on')->latest('created_at')->paginate($perPage);
        $payments = PlatformPayment::query()->where('tenant_id', $tenantId)->with('invoice:id,number')->latest('verified_at')->latest('created_at')->paginate($perPage);

        return response()->json(['data' => [
            'invoices' => $invoices->through(fn (PlatformInvoice $invoice): array => $this->invoicePayload($invoice)),
            'payments' => $payments->through(fn (PlatformPayment $payment): array => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => $payment->invoice?->number,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency_code' => $payment->currency_code,
                'verified_at' => $payment->verified_at?->toISOString(),
            ]),
        ], 'meta' => [
            'invoices' => ['current_page' => $invoices->currentPage(), 'last_page' => $invoices->lastPage(), 'total' => $invoices->total()],
            'payments' => ['current_page' => $payments->currentPage(), 'last_page' => $payments->lastPage(), 'total' => $payments->total()],
        ]]);
    }

    public function invoice(Request $request, PlatformInvoice $invoice, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertBillingAccess($request, $authorization, $tenantId);
        abort_unless($invoice->tenant_id === $tenantId, 404);
        $invoice->load(['subscription.plan:id,code,name', 'items', 'payments']);

        return response()->json(['data' => [
            ...$this->invoicePayload($invoice),
            'items' => $invoice->items->map(fn ($item): array => $item->only(['id', 'description', 'quantity', 'unit_price', 'amount']))->values(),
            'payments' => $invoice->payments->map(fn (PlatformPayment $payment): array => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency_code' => $payment->currency_code,
                'verified_at' => $payment->verified_at?->toISOString(),
            ])->values(),
        ]]);
    }

    public function team(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->assertBillingAccess($request, $authorization, $tenantId);
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $members = TenantMembership::query()->where('tenant_id', $tenantId)->with([
            'user:id,name,email,status,last_login_at,created_at',
            'user.roleAssignments' => fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('company_id')->with('role:id,name,slug'),
        ])->orderBy('created_at')->paginate($perPage);

        return response()->json(['data' => $members->through(function (TenantMembership $membership): array {
            $user = $membership->user;
            return [
                'id' => $membership->id,
                'user_id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'status' => $membership->status,
                'user_status' => $user?->status,
                'roles' => $user?->roleAssignments?->map(fn ($assignment) => $assignment->role?->only(['id', 'name', 'slug']))->filter()->values()->all() ?? [],
                'last_login_at' => $user?->last_login_at?->toISOString(),
                'joined_at' => $membership->created_at?->toISOString(),
            ];
        })]);
    }

    private function assertBillingAccess(Request $request, AuthorizationService $authorization, string $tenantId): void
    {
        abort_unless($authorization->allows($request->user(), $tenantId, 'organization.manage'), 403);
    }

    private function invoicePayload(PlatformInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'billing_period_key' => $invoice->billing_period_key,
            'status' => $invoice->status,
            'currency_code' => $invoice->currency_code,
            'subtotal' => $invoice->subtotal,
            'tax_total' => $invoice->tax_total,
            'total' => $invoice->total,
            'paid_total' => $invoice->paid_total,
            'issued_on' => $invoice->issued_on?->toDateString(),
            'due_on' => $invoice->due_on?->toDateString(),
            'plan' => $invoice->subscription?->plan?->only(['id', 'code', 'name']),
        ];
    }
}
