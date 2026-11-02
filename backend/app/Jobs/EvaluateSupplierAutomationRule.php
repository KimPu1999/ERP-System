<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasTenantContext;
use App\Models\{ApprovalRequest, AutomationRule, AutomationRun, ErpNotification, Supplier, SupplierBill, SupplierDocument, SupplierDocumentRequirement, SupplierNotificationDelivery, SupplierOnboardingApplication, SupplierPurchasingHold, SupplierRiskAssessment, User};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EvaluateSupplierAutomationRule implements ShouldQueue
{
    use Dispatchable, Queueable, HasTenantContext;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(string $tenantId, public readonly string $ruleId, public readonly string $periodKey)
    {
        $this->tenantId = $tenantId;
    }

    public function backoff(): array { return [60, 300, 900]; }

    public function handle(): void
    {
        $rule = AutomationRule::where('tenant_id', $this->tenantId)->where('is_active', true)->find($this->ruleId);
        if (! $rule || ! in_array($rule->event_key, AutomationRule::SUPPLIER_ALLOWLIST, true)) return;

        $idempotencyKey = 'supplier:'.$rule->id.':'.$this->periodKey;
        $run = DB::transaction(function () use ($rule, $idempotencyKey): AutomationRun {
            $existing = AutomationRun::where('tenant_id', $this->tenantId)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing?->state === 'completed') return $existing;
            $run = $existing ?? AutomationRun::create(['tenant_id' => $this->tenantId, 'company_id' => $rule->company_id, 'rule_id' => $rule->id, 'automation_type' => $rule->event_key, 'idempotency_key' => $idempotencyKey, 'state' => 'queued', 'attempts' => 0]);
            $run->update(['state' => 'running', 'trigger_type' => 'scheduled', 'started_at' => now(), 'correlation_id' => $run->correlation_id ?: (string) Str::uuid(), 'attempts' => ((int) $run->attempts) + 1, 'last_error' => null, 'failure_classification' => null]);
            return $run;
        });
        if ($run->state === 'completed') return;

        try {
            $notifications = [];
            $matches = $this->matches($rule);
            foreach ($matches as $match) {
                $sourceKey = $rule->id.':'.$this->periodKey.':'.$match['source_key'];
                foreach ($this->recipientIds($rule, $match['company_id']) as $userId) {
                    foreach ($this->channels($rule, $userId, $match['company_id']) as $channel) {
                        $notification = ErpNotification::firstOrCreate([
                            'tenant_id' => $this->tenantId,
                            'user_id' => $userId,
                            'type' => $rule->event_key,
                            'source_key' => $sourceKey,
                            'channel' => $channel,
                        ], [
                            'rule_id' => $rule->id,
                            'automation_run_id' => $run->id,
                            'supplier_id' => $match['supplier_id'],
                            'company_id' => $match['company_id'],
                            'title' => $match['title'],
                            'body' => $match['body'],
                            'priority' => $match['priority'],
                            'delivery_state' => 'pending',
                        ]);
                        $delivery = SupplierNotificationDelivery::firstOrCreate([
                            'notification_id' => $notification->id,
                            'channel' => $channel,
                        ], ['tenant_id' => $this->tenantId, 'state' => 'queued', 'queued_at' => now()]);
                        if ($delivery->wasRecentlyCreated || $delivery->state === 'failed') $notifications[] = [$notification->id, $channel];
                    }
                }
            }
            $completedAt = now();
            $first = $matches[0] ?? null;
            AutomationRun::whereKey($run->id)->update([
                'state' => 'completed',
                'completed_at' => $completedAt,
                'duration_ms' => $run->started_at ? (int) max(0, round($run->started_at->diffInMilliseconds($completedAt))) : null,
                'source_type' => $first ? ($first['supplier_id'] ? 'supplier' : 'purchasing_record') : 'supplier_automation_batch',
                'source_id' => $first && Str::isUuid((string) ($first['supplier_id'] ?? $first['source_key'])) ? ($first['supplier_id'] ?? $first['source_key']) : null,
                'source_key' => $this->periodKey,
                'last_error' => null,
                'failure_classification' => null,
            ]);
            foreach ($notifications as [$notificationId, $channel]) DeliverSupplierNotification::dispatch($this->tenantId, $notificationId, $channel);
        } catch (\Throwable $exception) {
            AutomationRun::whereKey($run->id)->update(['state' => 'failed', 'completed_at' => now(), 'duration_ms' => $run->started_at ? (int) max(0, round($run->started_at->diffInMilliseconds(now()))) : null, 'last_error' => substr($exception->getMessage(), 0, 1000), 'failure_classification' => $this->failureClassification($exception)]);
            throw $exception;
        }
    }

    private function failureClassification(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \Illuminate\Validation\ValidationException => 'validation',
            $exception instanceof \Illuminate\Database\QueryException => 'database',
            $exception instanceof \Illuminate\Auth\Access\AuthorizationException => 'authorization',
            default => 'execution',
        };
    }

    private function matches(AutomationRule $rule): array
    {
        $config = (array) ($rule->conditions ?? []);
        $supplierId = $config['supplier_id'] ?? null;
        $company = $rule->company_id;
        $limit = 200;
        return match ($rule->event_key) {
            'supplier_onboarding_reminder' => SupplierOnboardingApplication::where('tenant_id', $this->tenantId)->whereIn('status', ['submitted', 'under_review', 'pending'])->when($company, fn ($q) => $q->where('company_id', $company))->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->where(function ($q) use ($config) { $q->where('submitted_at', '<=', now()->subDays((int) ($config['days_before'] ?? 7)))->orWhere(function ($x) use ($config) { $x->whereNull('submitted_at')->where('created_at', '<=', now()->subDays((int) ($config['days_before'] ?? 7))); }); })->with('supplier')->limit($limit)->get()->map(fn ($row) => $this->match((string) $row->id, $row->supplier_id, $row->company_id, 'Supplier onboarding reminder', 'A supplier onboarding application is waiting for review.', $row->supplier?->name))->all(),
            'supplier_missing_document_reminder' => $this->missingDocuments($rule, $supplierId, $company, $config, $limit),
            'supplier_risk_alert' => SupplierRiskAssessment::where('tenant_id', $this->tenantId)->where('status', 'approved')->whereIn('final_level', ['high', 'critical'])->when($company, fn ($q) => $q->where('company_id', $company))->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with('supplier')->latest()->limit($limit)->get()->map(fn ($row) => $this->match((string) $row->id, $row->supplier_id, $row->company_id, 'Supplier risk alert', 'An approved supplier risk assessment requires attention.', $row->supplier?->name, 'high'))->all(),
            'supplier_overdue_bill_reminder' => SupplierBill::where('tenant_id', $this->tenantId)->whereIn('state', ['posted', 'approved'])->whereDate('due_date', '<', now()->toDateString())->when($company, fn ($q) => $q->where('company_id', $company))->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with('supplier')->latest('due_date')->limit($limit)->get()->map(fn ($row) => $this->match((string) $row->id, $row->supplier_id, $row->company_id, 'Overdue supplier bill', 'Supplier bill '.$row->number.' is past its due date.', $row->supplier?->name, 'high'))->all(),
            'supplier_purchase_approval_reminder' => ApprovalRequest::where('tenant_id', $this->tenantId)->where('document_type', 'App\\Models\\PurchaseOrder')->where('state', 'pending')->when($company, fn ($q) => $q->where('company_id', $company))->where('created_at', '<=', now()->subDays((int) ($config['days_overdue'] ?? 1)))->limit($limit)->get()->map(fn ($row) => $this->match((string) $row->id, null, $row->company_id, 'Purchase approval reminder', 'A purchase order approval is waiting for an authorized decision.', null, 'high'))->all(),
            'supplier_hold_review_alert' => SupplierPurchasingHold::where('tenant_id', $this->tenantId)->where('status', 'active')->whereNotNull('review_due_date')->whereDate('review_due_date', '<=', now()->addDays((int) ($config['days_open'] ?? 7))->toDateString())->when($company, fn ($q) => $q->where('company_id', $company))->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with('supplier')->limit($limit)->get()->map(fn ($row) => $this->match((string) $row->id, $row->supplier_id, $row->company_id, 'Supplier hold review alert', 'An active supplier purchasing hold is due for review.', $row->supplier?->name, 'high'))->all(),
            default => [],
        };
    }

    private function missingDocuments(AutomationRule $rule, ?string $supplierId, ?string $company, array $config, int $limit): array
    {
        $suppliers = Supplier::where('tenant_id', $this->tenantId)->where('status', 'active')->when($company, fn ($q) => $q->where(function ($x) use ($company) { $x->whereNull('company_id')->orWhere('company_id', $company); }))->when($supplierId, fn ($q) => $q->whereKey($supplierId))->limit($limit)->get();
        $required = SupplierDocumentRequirement::where('tenant_id', $this->tenantId)->where('is_active', true)->where('is_required', true)->when($company, fn ($q) => $q->where(function ($x) use ($company) { $x->whereNull('company_id')->orWhere('company_id', $company); }))->get();
        return $suppliers->filter(function ($supplier) use ($required): bool {
            return $required->contains(fn ($requirement) => ! SupplierDocument::where('tenant_id', $this->tenantId)->where('supplier_id', $supplier->id)->where('document_type', $requirement->name)->whereIn('status', ['verified', 'approved'])->exists());
        })->map(fn ($supplier) => $this->match('supplier:'.$supplier->id.':missing-documents', $supplier->id, $supplier->company_id ?: null, 'Missing supplier documents', 'Required supplier documents are missing or not verified.', $supplier->name))->values()->all();
    }

    private function recipientIds(AutomationRule $rule, ?string $companyId): array
    {
        $configured = collect((array) $rule->recipients)->filter(fn ($id) => is_string($id))->values();
        $query = User::where('status', 'active')->whereHas('tenantMemberships', fn ($q) => $q->where('tenant_id', $this->tenantId)->where('status', 'active'))->whereHas('roleAssignments.role.permissions', fn ($q) => $q->whereIn('name', ['automation.manage', 'procurement.manage']));
        if ($companyId) $query->where(function ($q) use ($companyId) { $q->whereHas('roleAssignments', fn ($x) => $x->where('tenant_id', $this->tenantId)->where(function ($a) use ($companyId) { $a->whereNull('company_id')->orWhere('company_id', $companyId); }))->orWhereHas('companyMemberships', fn ($x) => $x->where('tenant_id', $this->tenantId)->where('company_id', $companyId)->where('status', 'active')); });
        if ($configured->isNotEmpty()) $query->whereIn('id', $configured->all());
        return $query->pluck('id')->all();
    }

    private function channels(AutomationRule $rule, string $userId, ?string $companyId): array
    {
        $configured = collect((array) $rule->channels)->filter(fn ($channel) => in_array($channel, ['in_app', 'email'], true))->values();
        $channels = $configured->isNotEmpty() ? $configured : collect(['in_app']);
        return $channels->filter(function (string $channel) use ($rule, $userId, $companyId): bool {
            $preference = DB::table('purchasing_notification_preferences')->where('tenant_id', $this->tenantId)->where('user_id', $userId)->where('event_type', $rule->event_key)->where('channel', $channel)->when($companyId, fn ($q) => $q->where(function ($x) use ($companyId) { $x->whereNull('company_id')->orWhere('company_id', $companyId); }))->orderByRaw('company_id IS NULL')->first();
            return $preference ? (bool) $preference->is_enabled : $channel === 'in_app';
        })->values()->all();
    }

    private function match(string $sourceKey, ?string $supplierId, ?string $companyId, string $title, string $body, ?string $supplierName, string $priority = 'normal'): array
    {
        return compact('sourceKey', 'supplierId', 'companyId', 'title', 'body', 'priority') + ['source_key' => $sourceKey, 'supplier_id' => $supplierId, 'company_id' => $companyId, 'supplier_name' => $supplierName];
    }
}
