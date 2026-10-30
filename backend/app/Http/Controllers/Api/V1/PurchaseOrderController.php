<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountingCurrency, Branch, Company, ProductVariant, PurchaseOrder, PurchaseOrderItem, Supplier, Warehouse};
use App\Services\{AuditLogger, AuthorizationService, ProcurementNumberService, ProcurementStateService, PurchaseOrderApprovalService};
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
            ->with(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product'])
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
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->string('supplier_id')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('order_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('order_date', '<=', $request->date('date_to')));
        $sort = in_array($request->string('sort')->toString(), ['number', 'state', 'expected_at', 'created_at'], true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $page = $query->orderBy($sort, $direction)->paginate(min(max($request->integer('per_page', 25), 10), 100));
        return response()->json(['data' => $page, 'summary' => $this->summary($tenantId, $companyId, $branchId, $accessibleCompanyIds, $request)]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        return response()->json(['data' => $purchaseOrder->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product', 'approvalRequest.definition', 'approvalRequest.actions.approver', 'approvalRequests.actions.approver'])]);
    }

    public function store(Request $request, ProcurementNumberService $numbers, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $this->validatedPayload($request, $tenantId);
        $this->assertWriteScope($request, $authorization, $tenantId, $data['company_id'], $data['branch_id'] ?? null);
        $rate = (string) ($data['exchange_rate'] ?? '1');
        $totals = $this->calculateTotals($data['items'], (string) ($data['shipping_total'] ?? '0'));
        $baseTotal = Decimal::mul($totals['grand_total'], $rate);
        $order = DB::transaction(function () use ($data, $tenantId, $request, $numbers, $rate, $baseTotal, $totals): PurchaseOrder {
            $model = PurchaseOrder::create(['tenant_id' => $tenantId, 'company_id' => $data['company_id'], 'branch_id' => $data['branch_id'] ?? null, 'warehouse_id' => $data['warehouse_id'] ?? null, 'currency_id' => $data['currency_id'] ?? null, 'exchange_rate' => $rate, 'base_total' => $baseTotal, 'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount_total'], 'tax_total' => $totals['tax_total'], 'shipping_total' => $totals['shipping_total'], 'grand_total' => $totals['grand_total'], 'supplier_id' => $data['supplier_id'], 'created_by' => $request->user()->id, 'number' => $numbers->makeFor('PO', $tenantId, $data['company_id'], $data['branch_id'] ?? null), 'state' => 'draft', 'order_date' => $data['order_date'] ?? now()->toDateString(), 'expected_at' => $data['expected_at'] ?? null, 'supplier_reference' => $data['supplier_reference'] ?? null, 'payment_terms' => $data['payment_terms'] ?? null, 'delivery_address' => $data['delivery_address'] ?? null, 'notes' => $data['notes'] ?? null]);
            foreach ($totals['items'] as $item) PurchaseOrderItem::create(['tenant_id' => $tenantId, 'purchase_order_id' => $model->id, 'variant_id' => $item['variant_id'], 'description' => $item['description'] ?? null, 'ordered_quantity' => $item['ordered_quantity'], 'received_quantity' => '0', 'unit_price' => $item['unit_price'], 'discount_type' => $item['discount_type'], 'discount_amount' => $item['discount_amount'], 'tax_rate' => $item['tax_rate'], 'tax_amount' => $item['tax_amount'], 'line_subtotal' => $item['line_subtotal'], 'line_total' => $item['line_total']]);
            return $model;
        });
        $audit->record($request, 'purchase_order.created', $tenantId, $order);
        return response()->json(['data' => $order->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product'])], 201);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, AuditLogger $audit, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        abort_unless($purchaseOrder->state === 'draft', 422, 'Only draft purchase orders can be edited.');
        $data = $this->validatedPayload($request, (string) $purchaseOrder->tenant_id);
        $this->assertWriteScope($request, $authorization, (string) $purchaseOrder->tenant_id, $data['company_id'], $data['branch_id'] ?? null);
        $rate = (string) ($data['exchange_rate'] ?? '1');
        $totals = $this->calculateTotals($data['items'], (string) ($data['shipping_total'] ?? '0'));
        DB::transaction(function () use ($data, $purchaseOrder, $rate, $totals): void {
            $locked = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['updated_at']) && $locked->updated_at?->toISOString() !== $data['updated_at']) abort(409, 'This purchase order changed after it was loaded. Refresh before saving again.');
            $locked->update(['company_id' => $data['company_id'], 'branch_id' => $data['branch_id'] ?? null, 'warehouse_id' => $data['warehouse_id'] ?? null, 'currency_id' => $data['currency_id'] ?? null, 'exchange_rate' => $rate, 'base_total' => Decimal::mul($totals['grand_total'], $rate), 'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount_total'], 'tax_total' => $totals['tax_total'], 'shipping_total' => $totals['shipping_total'], 'grand_total' => $totals['grand_total'], 'supplier_id' => $data['supplier_id'], 'order_date' => $data['order_date'] ?? $locked->order_date?->toDateString() ?? now()->toDateString(), 'expected_at' => $data['expected_at'] ?? null, 'supplier_reference' => $data['supplier_reference'] ?? null, 'payment_terms' => $data['payment_terms'] ?? null, 'delivery_address' => $data['delivery_address'] ?? null, 'notes' => $data['notes'] ?? null]);
            $locked->items()->delete();
            foreach ($totals['items'] as $item) PurchaseOrderItem::create(['tenant_id' => $locked->tenant_id, 'purchase_order_id' => $locked->id, 'variant_id' => $item['variant_id'], 'description' => $item['description'] ?? null, 'ordered_quantity' => $item['ordered_quantity'], 'received_quantity' => '0', 'unit_price' => $item['unit_price'], 'discount_type' => $item['discount_type'], 'discount_amount' => $item['discount_amount'], 'tax_rate' => $item['tax_rate'], 'tax_amount' => $item['tax_amount'], 'line_subtotal' => $item['line_subtotal'], 'line_total' => $item['line_total']]);
        });
        $audit->record($request, 'purchase_order.updated', (string) $purchaseOrder->tenant_id, $purchaseOrder);
        return response()->json(['data' => $purchaseOrder->fresh()->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product'])]);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderApprovalService $approvals, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        return response()->json(['data' => $approvals->submit($request, $purchaseOrder)]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderApprovalService $approvals): JsonResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000'], 'comment' => ['nullable', 'string', 'max:2000']]);
        return response()->json(['data' => $approvals->decide($request, $purchaseOrder, 'approved', $data['remarks'] ?? $data['comment'] ?? null)]);
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderApprovalService $approvals): JsonResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'min:2', 'max:2000']]);
        return response()->json(['data' => $approvals->decide($request, $purchaseOrder, 'rejected', $data['remarks'])]);
    }

    public function requestRevision(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderApprovalService $approvals): JsonResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'min:2', 'max:2000']]);
        return response()->json(['data' => $approvals->decide($request, $purchaseOrder, 'revision_required', $data['remarks'])]);
    }

    public function revise(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderApprovalService $approvals, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderAccess($request, $purchaseOrder, $authorization);
        return response()->json(['data' => $approvals->returnToDraft($request, $purchaseOrder)]);
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
        $data = $request->validate(['company_id' => ['required', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'warehouse_id' => ['nullable', 'uuid'], 'currency_id' => ['nullable', 'uuid'], 'exchange_rate' => ['nullable', 'regex:/^\d+(\.\d{1,12})?$/'], 'supplier_id' => ['required', 'uuid'], 'order_date' => ['nullable', 'date'], 'expected_at' => ['nullable', 'date', 'after_or_equal:order_date'], 'supplier_reference' => ['nullable', 'string', 'max:120'], 'payment_terms' => ['nullable', 'string', 'max:500'], 'delivery_address' => ['nullable', 'string', 'max:2000'], 'shipping_total' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/'], 'notes' => ['nullable', 'string', 'max:5000'], 'updated_at' => ['nullable', 'date'], 'items' => ['required', 'array', 'min:1', 'max:500'], 'items.*.variant_id' => ['required', 'uuid'], 'items.*.description' => ['nullable', 'string', 'max:1000'], 'items.*.ordered_quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'items.*.unit_price' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'items.*.discount_type' => ['nullable', 'in:amount,percent'], 'items.*.discount_amount' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/'], 'items.*.tax_rate' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/']]);
        abort_unless(Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->exists(), 404);
        if (! empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenantId)->whereKey($data['branch_id'])->where('company_id', $data['company_id'])->where('status', 'active')->exists(), 404);
        if (! empty($data['warehouse_id'])) abort_unless(Warehouse::where('tenant_id', $tenantId)->whereKey($data['warehouse_id'])->where('company_id', $data['company_id'])->when($data['branch_id'] ?? null, fn ($q, $branchId) => $q->where('branch_id', $branchId))->where('is_active', true)->exists(), 404);
        if (! empty($data['currency_id'])) abort_unless(AccountingCurrency::where('tenant_id', $tenantId)->whereKey($data['currency_id'])->exists(), 404);
        abort_unless(Supplier::whereKey($data['supplier_id'])->where('tenant_id', $tenantId)->where('status', 'active')->exists(), 404);
        $variantIds = collect($data['items'])->pluck('variant_id');
        if ($variantIds->duplicates()->isNotEmpty()) throw ValidationException::withMessages(['items' => ['The same product variant cannot be added twice. Use a separate variant or consolidate the quantity.']]);
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
        $query = PurchaseOrder::query()->where('tenant_id', $tenantId)->when(is_array($accessibleCompanyIds), fn ($q) => $q->whereIn('company_id', $accessibleCompanyIds))->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->when($request->filled('date_from'), fn ($q) => $q->whereDate('order_date', '>=', $request->date('date_from')))->when($request->filled('date_to'), fn ($q) => $q->whereDate('order_date', '<=', $request->date('date_to')));
        $orders = (clone $query)->get(['state', 'base_total']);
        $company = $companyId ? Company::where('tenant_id', $tenantId)->find($companyId) : null;
        $missingBaseTotal = $orders->contains(fn ($order) => $order->base_total === null);
        $value = '0';
        foreach ($orders->whereIn('state', ['submitted', 'approved', 'partially_received']) as $order) $value = Decimal::add($value, (string) ($order->base_total ?? '0'));
        return ['total' => $orders->count(), 'draft' => $orders->where('state', 'draft')->count(), 'submitted' => $orders->where('state', 'submitted')->count(), 'pending_approvals' => $orders->where('state', 'submitted')->count(), 'approved' => $orders->where('state', 'approved')->count(), 'cancelled' => $orders->where('state', 'cancelled')->count(), 'pending_receipts' => $orders->whereIn('state', ['approved', 'partially_received'])->count(), 'outstanding_value' => ['available' => $company && ! $missingBaseTotal, 'value' => $company && ! $missingBaseTotal ? $value : null, 'currency_code' => $company?->base_currency, 'reason' => $company ? ($missingBaseTotal ? 'Some legacy orders do not have a base-currency total.' : null) : 'Select one company to calculate a currency-safe value.']];
    }

    private function calculateTotals(array $items, string $shippingTotal): array
    {
        $subtotal = '0'; $discountTotal = '0'; $taxTotal = '0'; $normalized = [];
        foreach ($items as $item) {
            $lineSubtotal = Decimal::mul((string) $item['ordered_quantity'], (string) $item['unit_price']);
            $discountType = $item['discount_type'] ?? 'amount'; $discountInput = (string) ($item['discount_amount'] ?? '0');
            $discount = $discountType === 'percent' ? Decimal::div(Decimal::mul($lineSubtotal, $discountInput), '100') : Decimal::normalize($discountInput);
            if (Decimal::compare($discount, $lineSubtotal) > 0) throw ValidationException::withMessages(['items' => ['A line discount cannot exceed its line subtotal.']]);
            $taxable = Decimal::sub($lineSubtotal, $discount); $taxRate = (string) ($item['tax_rate'] ?? '0');
            if (Decimal::compare($taxRate, '100') > 0) throw ValidationException::withMessages(['items' => ['Tax rates cannot exceed 100%.']]);
            $tax = Decimal::div(Decimal::mul($taxable, $taxRate), '100'); $lineTotal = Decimal::add($taxable, $tax);
            $subtotal = Decimal::add($subtotal, $lineSubtotal); $discountTotal = Decimal::add($discountTotal, $discount); $taxTotal = Decimal::add($taxTotal, $tax);
            $normalized[] = [...$item, 'discount_type' => $discountType, 'discount_amount' => $discount, 'tax_rate' => Decimal::normalize($taxRate), 'tax_amount' => $tax, 'line_subtotal' => $lineSubtotal, 'line_total' => $lineTotal];
        }
        $shipping = Decimal::normalize($shippingTotal); $grandTotal = Decimal::add(Decimal::add(Decimal::sub($subtotal, $discountTotal), $taxTotal), $shipping);
        return ['items' => $normalized, 'subtotal' => $subtotal, 'discount_total' => $discountTotal, 'tax_total' => $taxTotal, 'shipping_total' => $shipping, 'grand_total' => $grandTotal];
    }

}
