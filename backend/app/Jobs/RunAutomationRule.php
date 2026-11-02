<?php
namespace App\Jobs;
use App\Jobs\Concerns\HasTenantContext;
use App\Models\{AutomationRule, AutomationRun, ErpNotification, User};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

final class RunAutomationRule implements ShouldQueue
{
    use Queueable, HasTenantContext;

    public int $tries = 3;

    public function __construct(string $tenantId, public readonly string $ruleId, public readonly string $idempotencyKey)
    {
        $this->tenantId = $tenantId;
    }

    public function backoff(): array { return [60, 300, 900]; }

    public function handle(): void
    {
        try {
            $run = DB::transaction(function (): AutomationRun {
                $existing = AutomationRun::where('tenant_id', $this->tenantId)
                    ->where('idempotency_key', $this->idempotencyKey)
                    ->lockForUpdate()->first();

                if ($existing?->state === 'completed') return $existing;

                $rule = AutomationRule::where('tenant_id', $this->tenantId)
                    ->where('is_active', true)->findOrFail($this->ruleId);

                $run = $existing ?? AutomationRun::create([
                    'tenant_id' => $this->tenantId,
                    'rule_id' => $rule->id,
                    'idempotency_key' => $this->idempotencyKey,
                    'state' => 'queued',
                    'attempts' => 0,
                ]);
                $run->update(['state' => 'running', 'attempts' => ((int) $run->attempts) + 1, 'last_error' => null]);
                return $run;
            });

            if ($run->state === 'completed') return;

            $rule = AutomationRule::where('tenant_id', $this->tenantId)
                ->where('is_active', true)->findOrFail($this->ruleId);
            $recipients = $this->recipientIds($rule);
            $createdNotificationIds = [];

            DB::transaction(function () use ($rule, $recipients, &$createdNotificationIds, $run): void {
                foreach ((array) $rule->actions as $action) {
                    $type = (string) ($action['type'] ?? '');
                    if (! in_array($type, ['internal_review_task', 'authorized_notification', 'renewal_reminder', 'escalation_alert'], true)) continue;
                    foreach ($recipients as $userId) {
                        $notification = ErpNotification::create([
                            'tenant_id' => $this->tenantId,
                            'user_id' => $userId,
                            'type' => 'supplier_automation',
                            'title' => $rule->name,
                            'body' => $this->messageFor($type, $rule),
                            'delivery_state' => 'pending',
                        ]);
                        $createdNotificationIds[] = $notification->id;
                    }
                }
                AutomationRun::whereKey($run->id)->update([
                    'state' => 'completed',
                    'completed_at' => now(),
                    'last_error' => null,
                ]);
            });

            // Delivery is explicitly in-app. Email/webhook delivery is not enabled by this job.
            foreach ($createdNotificationIds as $notificationId) {
                DeliverErpNotification::dispatch($this->tenantId, $notificationId);
            }
        } catch (\Throwable $e) {
            $this->markFailed($e);
            throw $e;
        }
    }

    private function recipientIds(AutomationRule $rule): array
    {
        $ids = collect((array) $rule->recipients)->filter(fn ($id) => is_string($id))->values();
        $query = User::query()->whereHas('tenantMemberships', fn ($q) => $q->where('tenant_id', $this->tenantId)->where('status', 'active'))
            ->where(function ($q): void {
                $q->whereHas('roleAssignments.role.permissions', fn ($p) => $p->whereIn('name', ['automation.manage', 'procurement.manage']));
            })->where('status', 'active');
        if ($ids->isNotEmpty()) $query->whereIn('id', $ids->all());
        elseif ($rule->created_by) $query->whereKey($rule->created_by);
        return $query->pluck('id')->all();
    }

    private function messageFor(string $type, AutomationRule $rule): string
    {
        return match ($type) {
            'renewal_reminder' => 'A supplier renewal reminder requires review.',
            'escalation_alert' => 'A supplier automation escalation requires attention.',
            'internal_review_task' => 'A supplier review task has been created for an authorized user.',
            default => 'An authorized supplier automation notification was generated.',
        } . ' Rule: ' . $rule->name;
    }

    private function markFailed(\Throwable $e): void
    {
        AutomationRun::where('tenant_id', $this->tenantId)->where('idempotency_key', $this->idempotencyKey)->update([
            'state' => 'failed',
            'last_error' => 'Automation execution failed after bounded retries.',
            'attempts' => DB::raw('attempts + 1'),
        ]);
    }

    public function failed(\Throwable $e): void { $this->markFailed($e); }
}
