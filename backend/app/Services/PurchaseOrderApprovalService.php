<?php

namespace App\Services;

use App\Events\PurchaseOrderApproved;
use App\Jobs\DeliverErpNotification;
use App\Models\{ApprovalAction, ApprovalDefinition, ApprovalRequest, ApprovalStep, CompanyMembership, ErpNotification, PurchaseOrder, User};
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PurchaseOrderApprovalService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly ProcurementStateService $states,
        private readonly AuditLogger $audit,
    ) {}

    public function submit(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $tenantId = (string) $purchaseOrder->tenant_id;
        abort_unless($tenantId === (string) app(\App\Support\TenantContext::class)->id(), 404);
        return DB::transaction(function () use ($request, $purchaseOrder, $tenantId): PurchaseOrder {
            $order = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            if ($order->state !== 'draft') throw ValidationException::withMessages(['state' => ['Only draft purchase orders can be submitted.']]);
            $definition = $this->definitionFor($order);
            $steps = $this->matchingSteps($definition, $order);
            $current = $steps->first();
            if (! $current) throw ValidationException::withMessages(['approval' => ['No active approval level matches this purchase order amount.']]);
            if ($this->eligibleUsers($order, $current->toArray(), $definition)->isEmpty()) throw ValidationException::withMessages(['approval' => ['No active authorized approver is configured for the first approval level.']]);
            $previous = ApprovalRequest::where('tenant_id', $tenantId)->where('document_type', PurchaseOrder::class)->where('document_id', $order->id)->latest('approval_cycle')->first();
            $cycle = ((int) $order->approval_cycle) + 1;
            $approval = ApprovalRequest::create([
                'tenant_id' => $tenantId, 'definition_id' => $definition->id, 'requester_id' => $request->user()->id,
                'company_id' => $order->company_id, 'document_type' => PurchaseOrder::class, 'document_id' => $order->id,
                'approval_cycle' => $cycle, 'amount' => $this->amount($order), 'currency_code' => $this->currency($order),
                'policy_snapshot' => $this->snapshot($definition, $steps), 'current_level' => $current->level,
                'state' => 'pending', 'submitted_at' => now(), 'previous_request_id' => $previous?->id,
            ]);
            $this->states->transition($order, 'submitted', $request, $tenantId);
            $order->update(['approval_request_id' => $approval->id, 'approval_cycle' => $cycle, 'approval_status' => 'pending']);
            $this->audit->record($request, 'purchase_order.submitted_for_approval', $tenantId, $order, ['approval_request_id' => $approval->id, 'approval_cycle' => $cycle, 'policy_version' => $definition->version]);
            $fresh = $order->fresh()->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product', 'approvalRequest.actions']);
            $this->notifyAfterCommit($tenantId, $this->eligibleUsers($fresh, $current->toArray(), $definition), 'purchase_order.approval_assigned', 'Purchase approval assigned', "Purchase order {$fresh->number} approval cycle {$cycle}, level {$current->level}, requires your approval.");
            return $fresh;
        });
    }

    public function decide(Request $request, PurchaseOrder $purchaseOrder, string $decision, ?string $remarks): PurchaseOrder
    {
        if (in_array($decision, ['rejected', 'revision_required'], true) && trim((string) $remarks) === '') {
            throw ValidationException::withMessages(['remarks' => ['A reason is required for rejection or revision requests.']]);
        }
        $tenantId = (string) $purchaseOrder->tenant_id;
        abort_unless($tenantId === (string) app(\App\Support\TenantContext::class)->id(), 404);
        return DB::transaction(function () use ($request, $purchaseOrder, $decision, $remarks, $tenantId): PurchaseOrder {
            $order = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $approval = ApprovalRequest::whereKey($order->approval_request_id)->with('definition')->lockForUpdate()->first();
            if (! $approval || $approval->state !== 'pending' || $order->state !== 'submitted') throw ValidationException::withMessages(['state' => ['There is no active approval request for this order.']]);
            $step = $this->stepFromSnapshot($approval);
            if (! $step || ! $this->canAct($approval, $order, $step, $request->user())) abort(403, 'You are not an eligible approver for the current approval level.');
            if (! $approval->definition->allow_self_approval && (string) $approval->requester_id === (string) $request->user()->id) abort(403, 'Self-approval is prohibited by policy.');
            $matching = collect($approval->policy_snapshot['steps'] ?? [])->filter(fn (array $candidate): bool => (int) $candidate['level'] > (int) $approval->current_level);
            $next = $matching->first();
            $previousStatus = $order->state;
            $newStatus = $decision === 'approved' && ! $next ? 'approved' : ($decision === 'approved' ? 'submitted' : ($decision === 'rejected' ? 'rejected' : 'revision_required'));
            ApprovalAction::create([
                'tenant_id' => $tenantId, 'approval_request_id' => $approval->id, 'approver_id' => $request->user()->id,
                'level' => $approval->current_level, 'decision' => $decision, 'comment' => $remarks,
                'previous_status' => $previousStatus, 'new_status' => $newStatus, 'policy_version' => $approval->definition->version,
                'request_correlation_id' => (string) Str::uuid(), 'acted_at' => now(),
            ]);
            if ($decision === 'approved' && $next) {
                if ($this->eligibleUsersFromSnapshot($order, $approval, $next)->isEmpty()) throw ValidationException::withMessages(['approval' => ['No active authorized approver is configured for the next approval level.']]);
                $approval->update(['current_level' => $next['level']]);
                $this->audit->record($request, 'purchase_order.approval_level_completed', $tenantId, $order, ['approval_request_id' => $approval->id, 'level' => $step['level']]);
                $fresh = $order->fresh()->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product', 'approvalRequest.actions']);
                $this->notifyAfterCommit($tenantId, $this->eligibleUsersFromSnapshot($fresh, $approval, $next), 'purchase_order.approval_assigned', 'Next purchase approval assigned', "Purchase order {$fresh->number} approval cycle {$approval->approval_cycle}, level {$next['level']}, requires your approval.");
                return $fresh;
            }
            $approval->update(['state' => $decision === 'approved' ? 'approved' : $decision, 'completed_at' => now()]);
            $this->states->transition($order, $newStatus, $request, $tenantId, $remarks);
            $order->update(['approval_status' => $decision === 'approved' ? 'approved' : $decision]);
            $this->audit->record($request, 'purchase_order.approval_decided', $tenantId, $order, ['approval_request_id' => $approval->id, 'decision' => $decision, 'level' => $step['level']]);
            if ($decision === 'approved') event(new PurchaseOrderApproved($order));
            $fresh = $order->fresh()->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product', 'approvalRequest.actions']);
            $this->notifyAfterCommit($tenantId, User::whereKey($approval->requester_id)->get(), 'purchase_order.approval_decided', 'Purchase order approval updated', "Purchase order {$fresh->number} approval cycle {$approval->approval_cycle} is now {$fresh->approval_status}.");
            return $fresh;
        });
    }

    public function returnToDraft(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $tenantId = (string) $purchaseOrder->tenant_id;
        abort_unless($tenantId === (string) app(\App\Support\TenantContext::class)->id(), 404);
        return DB::transaction(function () use ($request, $purchaseOrder, $tenantId): PurchaseOrder {
            $order = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            if ($order->state !== 'revision_required') throw ValidationException::withMessages(['state' => ['Only orders requiring revision can return to draft.']]);
            $this->states->transition($order, 'draft', $request, $tenantId, 'Returned for authorized revision.');
            $order->update(['approval_status' => 'not_submitted']);
            $this->audit->record($request, 'purchase_order.returned_to_draft', $tenantId, $order);
            return $order->fresh();
        });
    }

    public function canAct(ApprovalRequest $request, PurchaseOrder $order, array $step, User $user): bool
    {
        if ($order->company_id && ! CompanyMembership::where('tenant_id', $order->tenant_id)->where('company_id', $order->company_id)->where('user_id', $user->id)->where('status', 'active')->exists()) return false;
        if ($request->requester_id === $user->id && ! $request->definition->allow_self_approval) return false;
        return $this->authorization->allows($user, (string) $order->tenant_id, (string) $step['permission'], $order->company_id, $order->branch_id, $step['department_id'] ?? null);
    }

    public function visibleTo(ApprovalRequest $request, User $user): bool
    {
        $order = $request->purchaseOrder;
        $step = $this->stepFromSnapshot($request);
        return $order && $step && $this->canAct($request, $order, $step, $user);
    }

    public function definitionFor(PurchaseOrder $order): ApprovalDefinition
    {
        $currency = $this->currency($order);
        $query = ApprovalDefinition::where('tenant_id', $order->tenant_id)->where('scenario', 'purchase_order')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $order->company_id))
            ->where(fn ($q) => $q->whereNull('currency_code')->orWhere('currency_code', $currency))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()))
            ->with(['steps' => fn ($q) => $q->where('is_active', true)->orderBy('level')]);
        $definition = $query->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$order->company_id])->orderByDesc('version')->first();
        if (! $definition) throw ValidationException::withMessages(['approval' => ['No active purchase-order approval policy is configured for this company and currency.']]);
        return $definition;
    }

    private function matchingSteps(ApprovalDefinition $definition, PurchaseOrder $order)
    {
        return $definition->steps->filter(fn (ApprovalStep $step): bool => Decimal::compare($this->amount($order), (string) $step->minimum_amount) >= 0 && ($step->maximum_amount === null || Decimal::compare($this->amount($order), (string) $step->maximum_amount) <= 0))->values();
    }

    private function snapshot(ApprovalDefinition $definition, $steps): array
    {
        return ['definition_id' => $definition->id, 'version' => $definition->version, 'name' => $definition->name, 'allow_self_approval' => $definition->allow_self_approval, 'steps' => $steps->map(fn (ApprovalStep $step) => ['level' => $step->level, 'permission' => $step->permission, 'department_id' => $step->department_id, 'minimum_amount' => (string) $step->minimum_amount, 'maximum_amount' => $step->maximum_amount === null ? null : (string) $step->maximum_amount])->all()];
    }

    private function stepFromSnapshot(ApprovalRequest $request): ?array { return collect($request->policy_snapshot['steps'] ?? [])->first(fn (array $step): bool => (int) $step['level'] === (int) $request->current_level); }
    private function amount(PurchaseOrder $order): string { return (string) ($order->base_total ?? $order->grand_total ?? '0'); }
    private function currency(PurchaseOrder $order): ?string { return $order->currency?->code ?: $order->company?->base_currency; }

    private function eligibleUsers(PurchaseOrder $order, array $step, ApprovalDefinition $definition)
    {
        return User::whereHas('tenantMemberships', fn ($q) => $q->where('tenant_id', $order->tenant_id)->where('status', 'active'))->get()->filter(fn (User $user): bool => $this->canActForStep($order, $step, $definition, $user))->values();
    }
    private function eligibleUsersFromSnapshot(PurchaseOrder $order, ApprovalRequest $request, array $step)
    {
        return User::whereHas('tenantMemberships', fn ($q) => $q->where('tenant_id', $order->tenant_id)->where('status', 'active'))->get()->filter(fn (User $user): bool => $this->canAct($request, $order, $step, $user))->values();
    }
    private function canActForStep(PurchaseOrder $order, array $step, ApprovalDefinition $definition, User $user): bool
    {
        if ($order->company_id && ! CompanyMembership::where('tenant_id', $order->tenant_id)->where('company_id', $order->company_id)->where('user_id', $user->id)->where('status', 'active')->exists()) return false;
        if (! $definition->allow_self_approval && (string) $order->created_by === (string) $user->id) return false;
        return $this->authorization->allows($user, (string) $order->tenant_id, (string) $step['permission'], $order->company_id, $order->branch_id, $step['department_id'] ?? null);
    }

    private function notifyAfterCommit(string $tenantId, $users, string $type, string $title, string $body): void
    {
        $ids = $users->pluck('id')->unique()->values();
        DB::afterCommit(function () use ($tenantId, $ids, $type, $title, $body): void {
            foreach ($ids as $userId) {
                $notification = ErpNotification::firstOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId, 'type' => $type, 'body' => $body], ['title' => $title, 'delivery_state' => 'pending']);
                DeliverErpNotification::dispatch($tenantId, $notification->id);
            }
        });
    }
}
