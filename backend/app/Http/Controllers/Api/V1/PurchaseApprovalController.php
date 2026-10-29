<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{ApprovalDefinition, ApprovalRequest, ApprovalStep, Company, PurchaseOrder};
use App\Services\{AuthorizationService, PurchaseOrderApprovalService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseApprovalController
{
    public function inbox(Request $request, AuthorizationService $authorization, PurchaseOrderApprovalService $approvals): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $companyId = $request->string('company_id')->toString() ?: null;
        $branchId = $request->string('branch_id')->toString() ?: null;
        if ($companyId) abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage', $companyId, $branchId) || $authorization->allows($request->user(), $tenantId, 'approvals.manage', $companyId, $branchId), 403);
        $requests = ApprovalRequest::query()->where('tenant_id', $tenantId)->where('document_type', PurchaseOrder::class)->with(['definition', 'actions.approver', 'requester', 'purchaseOrder.supplier', 'purchaseOrder.company', 'purchaseOrder.branch', 'purchaseOrder.currency'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($branchId, fn ($q) => $q->whereHas('purchaseOrder', fn ($order) => $order->where('branch_id', $branchId)))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('date_to')))
            ->latest('submitted_at')->get()->filter(fn (ApprovalRequest $approval) => $approval->purchaseOrder && ($authorization->allows($request->user(), $tenantId, 'procurement.manage', $approval->company_id, $approval->purchaseOrder->branch_id) || $approvals->visibleTo($approval, $request->user())));
        if ($request->filled('search')) {
            $search = strtolower($request->string('search')->toString());
            $requests = $requests->filter(fn (ApprovalRequest $approval) => str_contains(strtolower((string) $approval->purchaseOrder?->number), $search) || str_contains(strtolower((string) $approval->purchaseOrder?->supplier?->name), $search));
        }
        if ($request->filled('state')) $requests = $requests->where('state', $request->string('state')->toString());
        if ($request->filled('approver_id')) $requests = $requests->filter(fn (ApprovalRequest $approval) => $approval->actions->contains('approver_id', $request->string('approver_id')->toString()) || $approvals->visibleTo($approval, $request->user()));
        if ($request->filled('amount_min')) $requests = $requests->filter(fn (ApprovalRequest $approval) => Decimal::compare((string) $approval->amount, $request->string('amount_min')->toString()) >= 0);
        if ($request->filled('amount_max')) $requests = $requests->filter(fn (ApprovalRequest $approval) => Decimal::compare((string) $approval->amount, $request->string('amount_max')->toString()) <= 0);
        $summary = ['pending' => $requests->where('state', 'pending')->count(), 'approved' => $requests->where('state', 'approved')->count(), 'rejected' => $requests->where('state', 'rejected')->count(), 'revision_required' => $requests->where('state', 'revision_required')->count()];
        $perPage = min(max($request->integer('per_page', 25), 10), 100); $page = max($request->integer('page', 1), 1); $items = $requests->forPage($page, $perPage)->values();
        return response()->json(['data' => new LengthAwarePaginator($items, $requests->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]), 'summary' => $summary]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder, AuthorizationService $authorization, PurchaseOrderApprovalService $approvals): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless((string) $purchaseOrder->tenant_id === $tenantId, 404);
        $canManage = $authorization->allows($request->user(), $tenantId, 'procurement.manage', $purchaseOrder->company_id, $purchaseOrder->branch_id)
            || $authorization->allows($request->user(), $tenantId, 'approvals.manage', $purchaseOrder->company_id, $purchaseOrder->branch_id)
            || $authorization->allows($request->user(), $tenantId, 'approvals.view', $purchaseOrder->company_id, $purchaseOrder->branch_id);
        $canViewAssigned = ApprovalRequest::where('tenant_id', $tenantId)->where('document_type', PurchaseOrder::class)->where('document_id', $purchaseOrder->id)->with('purchaseOrder')->get()->contains(fn (ApprovalRequest $approval): bool => $approvals->visibleTo($approval, $request->user()));
        abort_unless($canManage || $canViewAssigned, 403);
        return response()->json(['data' => ApprovalRequest::where('tenant_id', $tenantId)->where('document_type', PurchaseOrder::class)->where('document_id', $purchaseOrder->id)->with(['definition', 'actions.approver', 'requester'])->orderByDesc('approval_cycle')->get()]);
    }

    public function policies(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($authorization->allows($request->user(), $tenantId, 'approvals.manage') || $authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403);
        return response()->json(['data' => ApprovalDefinition::where('tenant_id', $tenantId)->where('scenario', 'purchase_order')->with('steps')->latest()->paginate(25)]);
    }

    public function storePolicy(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id(); $data = $this->validatedPolicy($request);
        $this->assertPolicyAccess($request, $authorization, $tenantId, $data['company_id']);
        $definition = DB::transaction(function () use ($data, $tenantId): ApprovalDefinition {
            $this->assertNoOverlap($data['steps']);
            $definition = ApprovalDefinition::create(['tenant_id' => $tenantId, 'company_id' => $data['company_id'], 'scenario' => 'purchase_order', 'name' => $data['name'], 'currency_code' => $data['currency_code'] ?? null, 'effective_from' => $data['effective_from'] ?? null, 'effective_to' => $data['effective_to'] ?? null, 'version' => 1, 'allow_self_approval' => false, 'is_active' => $data['is_active'] ?? true]);
            foreach ($data['steps'] as $step) ApprovalStep::create([...$step, 'tenant_id' => $tenantId, 'definition_id' => $definition->id, 'is_active' => true]);
            return $definition->load('steps');
        });
        return response()->json(['data' => $definition], 201);
    }

    public function updatePolicy(Request $request, ApprovalDefinition $approvalDefinition, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id(); abort_unless($approvalDefinition->tenant_id === $tenantId && $approvalDefinition->scenario === 'purchase_order', 404);
        $data = $this->validatedPolicy($request); $this->assertPolicyAccess($request, $authorization, $tenantId, $approvalDefinition->company_id);
        $definition = DB::transaction(function () use ($data, $approvalDefinition): ApprovalDefinition {
            $this->assertNoOverlap($data['steps']); $approvalDefinition->update(['name' => $data['name'], 'currency_code' => $data['currency_code'] ?? null, 'effective_from' => $data['effective_from'] ?? null, 'effective_to' => $data['effective_to'] ?? null, 'version' => ((int) $approvalDefinition->version) + 1, 'is_active' => $data['is_active'] ?? $approvalDefinition->is_active]); $approvalDefinition->steps()->delete(); foreach ($data['steps'] as $step) ApprovalStep::create([...$step, 'tenant_id' => $approvalDefinition->tenant_id, 'definition_id' => $approvalDefinition->id, 'is_active' => true]); return $approvalDefinition->fresh('steps');
        });
        return response()->json(['data' => $definition]);
    }

    private function validatedPolicy(Request $request): array
    {
        return $request->validate(['company_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:150'], 'currency_code' => ['nullable', 'string', 'size:3'], 'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'is_active' => ['sometimes', 'boolean'], 'steps' => ['required', 'array', 'min:1', 'max:20'], 'steps.*.level' => ['required', 'integer', 'min:1'], 'steps.*.permission' => ['required', 'string', 'max:100'], 'steps.*.department_id' => ['nullable', 'uuid'], 'steps.*.minimum_amount' => ['required', 'regex:/^\\d+(\\.\\d{1,6})?$/'], 'steps.*.maximum_amount' => ['nullable', 'regex:/^\\d+(\\.\\d{1,6})?$/']]);
    }
    private function assertPolicyAccess(Request $request, AuthorizationService $authorization, string $tenantId, string $companyId): void { abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->where('is_active', true)->exists(), 404); abort_unless($authorization->allows($request->user(), $tenantId, 'approvals.manage', $companyId) || $authorization->allows($request->user(), $tenantId, 'procurement.manage', $companyId), 403); }
    private function assertNoOverlap(array $steps): void
    {
        $levels = collect($steps)->pluck('level');
        if ($levels->duplicates()->isNotEmpty()) throw ValidationException::withMessages(['steps' => ['Approval levels must be unique.']]);
        foreach ($steps as $step) {
            if ($step['maximum_amount'] !== null && Decimal::compare((string) $step['minimum_amount'], (string) $step['maximum_amount']) > 0) {
                throw ValidationException::withMessages(['steps' => ['An approval level minimum cannot exceed its maximum.']]);
            }
        }
    }
}
