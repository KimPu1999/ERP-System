<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PurchaseOrderApproved;
use App\Models\{AccountingCurrency, Branch, Company, ProcurementApproval, ProductVariant, PurchaseOrder, PurchaseOrderItem, Supplier, User};
use App\Services\{AuditLogger, AuthorizationService, ProcurementNumberService, ProcurementStateService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseOrderController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $companyId = $request->string('company_id')->toString() ?: null;
        $branchId = $request->string('branch_id')->toString() ?: null;
        $this->assertListScope($request, $authorization, $tenantId, $companyId, $branchId);
        $accessibleCompanyIds = $companyId ? null : $authorization->accessibleCompanyIds($request->user(), $tenantId, 'procurement.manage');
        $query = PurchaseOrder::query()->where('tenant_id', $tenantId)
            ->with(['supplier', 'company', 'branch', 'currency', 'items.variant.product'])
            ->when(is_array($accessibleCompanyIds), fn ($q) => $q->whereIn('company_id', $accessibleCompanyIds))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(function ($inner) use ($search): void {
                    $inner->where('number', 'ilike', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')));
        $sort = in_array($request->string('sort')->toString(), ['number', 'state', 'expected_at', 'created_at'], true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $page = $query->orderBy($sort, $direction)->paginate(min(max($request->integer('per_page', 25), 10), 100));
        return response()->json(['data' => $page, 'summary' => $this->summary($tenantId, $companyId, $branchId, $accessibleCompanyIds, $request)]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        return response()->json(['data' => $purchaseOrder->load(['supplier', 'company', 'branch', 'currency', 'items.variant.product', 'approvals'])]);
    }

    public function store(Request $request, ProcurementNumberService $numbers, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $this->validatedPayload($request, $tenantId);
        $this->assertWriteScope($request, $authorization, $tenantId, $data['company_id'], $data['branch_id'] ?? null);
        $rate = (string) ($data['exchange_rate'] ?? '1');
        $baseTotal = $this->baseTotal($data['items'], $rate);
        $order = DB::transaction(function () use ($data, $tenantId, $request, $numbers, $rate, $baseTotal): PurchaseOrder {
            $model = PurchaseOrder::create(['tenant_id' => $tenantId, 'company_id' => $data['company_id'], 'branch_id' => $data['branch_id'] ?? null, 'currency_id' => $data['currency_id'] ?? null, 'exchange_rate' => $rate, 'base_total' => $baseTotal, 'supplier_id' => $data['supplier_id'], 'created_by' => $request->user()->id, 'number' => $numbers->make('PO'), 'state' => 'draft', 'expected_at' => $data['expected_at'] ?? null, 'notes' => $data['notes'] ?? null]);
            foreach ($data['items'] as $item) PurchaseOrderItem::create(['tenant_id' => $tenantId, 'purchase_order_id' => $model->id, 'variant_id' => $item['variant_id'], 'ordered_quantity' => $item['ordered_quantity'], 'received_quantity' => '0', 'unit_price' => $item['unit_price']]);
            return $model;
        });
        $audit->record($request, 'purchase_order.created', $tenantId, $order);
        return response()->json(['data' => $order->load(['supplier', 'company', 'branch', 'currency', 'items.variant.product'])], 201);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        abort_unless($purchaseOrder->state === 'draft', 422, 'Only draft purchase orders can be edited.');
        $data = $this->validatedPayload($request, (string) $purchaseOrder->tenant_id);
        $this->assertWriteScope($request, $authorization, (string) $purchaseOrder->tenant_id, $data['company_id'], $data['branch_id'] ?? null);
        $rate = (string) ($data['exchange_rate'] ?? '1');
        DB::transaction(function () use ($data, $purchaseOrder, $rate): void {
            $purchaseOrder->update(['company_id' => $data['company_id'], 'branch_id' => $data['branch_id'] ?? null, 'currency_id' => $data['currency_id'] ?? null, 'exchange_rate' => $rate, 'base_total' => $this->baseTotal($data['items'], $rate), 'supplier_id' => $data['supplier_id'], 'expected_at' => $data['expected_at'] ?? null, 'notes' => $data['notes'] ?? null]);
            $purchaseOrder->items()->delete();
            foreach ($data['items'] as $item) PurchaseOrderItem::create(['tenant_id' => $purchaseOrder->tenant_id, 'purchase_order_id' => $purchaseOrder->id, 'variant_id' => $item['variant_id'], 'ordered_quantity' => $item['ordered_quantity'], 'received_quantity' => '0', 'unit_price' => $item['unit_price']]);
        });
        $audit->record($request, 'purchase_order.updated', (string) $purchaseOrder->tenant_id, $purchaseOrder);
        return response()->json(['data' => $purchaseOrder->fresh()->load(['supplier', 'company', 'branch', 'currency', 'items.variant.product'])]);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, ProcurementStateService $states, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        $data = $request->validate(['approver_ids' => ['nullable', 'array', 'max:10'], 'approver_ids.*' => ['uuid', 'exists:users,id']]);
        $approvers = $this->eligibleApprovers($purchaseOrder, $data['approver_ids'] ?? null, $request->user()->id, $authorization);
        $order = DB::transaction(function () use ($purchaseOrder, $states, $request, $approvers): PurchaseOrder {
            $model = $states->transition($purchaseOrder, 'submitted', $request, (string) $purchaseOrder->tenant_id);
            foreach ($approvers as $level => $approverId) ProcurementApproval::create(['tenant_id' => $purchaseOrder->tenant_id, 'document_type' => PurchaseOrder::class, 'document_id' => $model->id, 'approver_id' => $approverId, 'level' => $level + 1, 'state' => 'pending']);
            return $model;
        });
        $audit->record($request, 'purchase_order.submitted', (string) $purchaseOrder->tenant_id, $order);
        return response()->json(['data' => $order->fresh()]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, ProcurementStateService $states, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        abort_unless((string) $purchaseOrder->created_by !== (string) $request->user()->id, 403, 'The creator cannot approve their own purchase order.');
        $approval = ProcurementApproval::where('tenant_id', $purchaseOrder->tenant_id)->where('document_type', PurchaseOrder::class)->where('document_id', $purchaseOrder->id)->where('approver_id', $request->user()->id)->where('state', 'pending')->orderBy('level')->lockForUpdate()->firstOrFail();
        $approval->update(['state' => 'approved', 'comment' => $request->string('comment')->toString(), 'decided_at' => now()]);
        $pending = ProcurementApproval::where('tenant_id', $purchaseOrder->tenant_id)->where('document_type', PurchaseOrder::class)->where('document_id', $purchaseOrder->id)->where('state', 'pending')->exists();
        $order = $purchaseOrder;
        if (! $pending) { $order = $states->transition($purchaseOrder, 'approved', $request, (string) $purchaseOrder->tenant_id); event(new PurchaseOrderApproved($order)); }
        $audit->record($request, 'purchase_order.approval.recorded', (string) $purchaseOrder->tenant_id, $order);
        return response()->json(['data' => $order->fresh()]);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, ProcurementStateService $states, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        $order = $states->transition($purchaseOrder, 'cancelled', $request, (string) $purchaseOrder->tenant_id, $request->string('comment')->toString());
        $audit->record($request, 'purchase_order.cancelled', (string) $purchaseOrder->tenant_id, $order);
        return response()->json(['data' => $order]);
    }

    private function validatedPayload(Request $request, string $tenantId): array
    {
        $data = $request->validate(['company_id' => ['required', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'currency_id' => ['nullable', 'uuid'], 'exchange_rate' => ['nullable', 'numeric', 'gt:0'], 'supplier_id' => ['required', 'uuid'], 'expected_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:5000'], 'items' => ['required', 'array', 'min:1'], 'items.*.variant_id' => ['required', 'uuid'], 'items.*.ordered_quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'items.*.unit_price' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]);
        abort_unless(Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->exists(), 404);
        if (! empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenantId)->whereKey($data['branch_id'])->where('company_id', $data['company_id'])->where('status', 'active')->exists(), 404);
        if (! empty($data['currency_id'])) abort_unless(AccountingCurrency::where('tenant_id', $tenantId)->whereKey($data['currency_id'])->exists(), 404);
        abort_unless(Supplier::whereKey($data['supplier_id'])->where('tenant_id', $tenantId)->where('status', 'active')->exists(), 404);
        foreach ($data['items'] as $item) abort_unless(ProductVariant::whereKey($item['variant_id'])->where('tenant_id', $tenantId)->where('is_active', true)->exists(), 404);
        return $data;
    }

    private function assertWriteScope(Request $request, AuthorizationService $authorization, string $tenantId, string $companyId, ?string $branchId): void { abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage', $companyId, $branchId), 403); }

    private function assertOrderAccess(Request $request, PurchaseOrder $purchaseOrder, AuthorizationService $authorization): void
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless((string) $purchaseOrder->tenant_id === $tenantId, 404);
        $this->assertWriteScope($request, $authorization, $tenantId, (string) $purchaseOrder->company_id, $purchaseOrder->branch_id ? (string) $purchaseOrder->branch_id : null);
    }

    private function assertListScope(Request $request, AuthorizationService $authorization, string $tenantId, ?string $companyId, ?string $branchId): void
    {
        if ($companyId) $this->assertWriteScope($request, $authorization, $tenantId, $companyId, $branchId);
        else abort_unless($authorization->accessibleCompanyIds($request->user(), $tenantId, 'procurement.manage') !== [], 403);
        if ($branchId) abort_unless($companyId && Branch::where('tenant_id', $tenantId)->whereKey($branchId)->where('company_id', $companyId)->exists(), 404);
    }

    private function summary(string $tenantId, ?string $companyId, ?string $branchId, ?array $accessibleCompanyIds, Request $request): array
    {
        $query = PurchaseOrder::query()->where('tenant_id', $tenantId)->when(is_array($accessibleCompanyIds), fn ($q) => $q->whereIn('company_id', $accessibleCompanyIds))->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')));
        $orders = (clone $query)->get(['state', 'base_total']);
        $company = $companyId ? Company::where('tenant_id', $tenantId)->find($companyId) : null;
        $missingBaseTotal = $orders->contains(fn ($order) => $order->base_total === null);
        $value = '0';
        foreach ($orders->whereIn('state', ['submitted', 'approved', 'partially_received']) as $order) $value = Decimal::add($value, (string) ($order->base_total ?? '0'));
        return ['total' => $orders->count(), 'draft' => $orders->where('state', 'draft')->count(), 'pending_approvals' => $orders->where('state', 'submitted')->count(), 'approved' => $orders->where('state', 'approved')->count(), 'pending_receipts' => $orders->whereIn('state', ['approved', 'partially_received'])->count(), 'outstanding_value' => ['available' => $company && ! $missingBaseTotal, 'value' => $company && ! $missingBaseTotal ? $value : null, 'currency_code' => $company?->base_currency, 'reason' => $company ? ($missingBaseTotal ? 'Some legacy orders do not have a base-currency total.' : null) : 'Select one company to calculate a currency-safe value.']];
    }

    private function baseTotal(array $items, string $rate): string { $total = '0'; foreach ($items as $item) $total = Decimal::add($total, Decimal::mul((string) $item['ordered_quantity'], Decimal::mul((string) $item['unit_price'], $rate))); return $total; }

    private function eligibleApprovers(PurchaseOrder $order, ?array $requested, string $creatorId, AuthorizationService $authorization): array
    {
        $ids = $requested ?? User::query()->where('id', '!=', $creatorId)->whereHas('tenantMemberships', fn ($q) => $q->where('tenant_id', $order->tenant_id)->where('status', 'active'))->limit(50)->pluck('id')->all();
        $eligible = collect($ids)->unique()->filter(fn ($id) => (string) $id !== (string) $creatorId && $authorization->allows(User::findOrFail($id), (string) $order->tenant_id, 'procurement.manage', (string) $order->company_id, $order->branch_id ? (string) $order->branch_id : null))->values()->all();
        if ($eligible === []) throw ValidationException::withMessages(['approver_ids' => ['At least one eligible approver other than the purchase order creator is required.']]);
        return $eligible;
    }
}
