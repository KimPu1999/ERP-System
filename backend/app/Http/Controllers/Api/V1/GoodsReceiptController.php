<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{CompanyMembership, GoodsReceipt, PurchaseOrder, PurchaseOrderItem, StockMovement, Warehouse, WarehouseLocation};
use App\Services\{AuditLogger, AuthorizationService, GoodsReceiptService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class GoodsReceiptController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenantId(); $companyId = $request->string('company_id')->toString() ?: null; $branchId = $request->string('branch_id')->toString() ?: null;
        $this->assertScope($request, $authorization, $tenantId, $companyId, $branchId);
        $accessible = $companyId ? null : $authorization->accessibleCompanyIds($request->user(), $tenantId, 'procurement.manage');
        if ($accessible === null) $accessible = CompanyMembership::where('tenant_id', $tenantId)->where('user_id', $request->user()->id)->where('status', 'active')->pluck('company_id')->all();
        $query = GoodsReceipt::query()->where('tenant_id', $tenantId)->with(['purchaseOrder.supplier', 'company', 'branch', 'warehouse', 'receivedBy', 'postedBy'])->when(is_array($accessible), fn ($q) => $q->whereIn('company_id', $accessible))->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->string('warehouse_id')->toString()))->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')->toString()))->when($request->filled('search'), function ($q) use ($request): void { $search = $request->string('search')->toString(); $q->where(function ($inner) use ($search): void { $inner->where('number', 'ilike', "%{$search}%")->orWhereHas('purchaseOrder', fn ($po) => $po->where('number', 'ilike', "%{$search}%"))->orWhereHas('purchaseOrder.supplier', fn ($supplier) => $supplier->where('name', 'ilike', "%{$search}%")); }); })->when($request->filled('date_from'), fn ($q) => $q->whereDate('receipt_date', '>=', $request->date('date_from')))->when($request->filled('date_to'), fn ($q) => $q->whereDate('receipt_date', '<=', $request->date('date_to')));
        $sort = in_array($request->string('sort')->toString(), ['number', 'state', 'receipt_date', 'created_at'], true) ? $request->string('sort')->toString() : 'created_at'; $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $page = $query->orderBy($sort, $direction)->paginate(min(max($request->integer('per_page', 25), 10), 100));
        $summaryQuery = GoodsReceipt::query()->where('tenant_id', $tenantId)->when(is_array($accessible), fn ($q) => $q->whereIn('company_id', $accessible))->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $receipts = (clone $summaryQuery)->with('items')->get();
        $pending = PurchaseOrderItem::where('tenant_id', $tenantId)->whereHas('purchaseOrder', fn ($q) => $q->whereIn('state', ['approved', 'partially_received'])->when(is_array($accessible), fn ($inner) => $inner->whereIn('company_id', $accessible))->when($companyId, fn ($inner) => $inner->where('company_id', $companyId))->when($branchId, fn ($inner) => $inner->where('branch_id', $branchId)))->get();
        $exceptions = $receipts->flatMap->items->filter(fn ($item) => Decimal::compare((string) $item->rejected_quantity, '0') > 0 || Decimal::compare((string) $item->damaged_quantity, '0') > 0);
        return response()->json(['data' => $page, 'summary' => ['total' => $receipts->count(), 'draft' => $receipts->where('state', 'draft')->count(), 'posted' => $receipts->where('state', 'posted')->count(), 'pending_po_quantity' => null, 'pending_po_quantity_available' => false, 'pending_po_quantity_reason' => 'Pending quantities use product-specific units and no conversion catalog is configured.', 'partially_received_pos' => $pending->filter(fn ($item) => Decimal::compare((string) $item->received_quantity, '0') > 0)->pluck('purchase_order_id')->unique()->count(), 'rejected_quantity' => null, 'rejected_quantity_available' => $exceptions->isEmpty(), 'rejected_quantity_reason' => $exceptions->isEmpty() ? null : 'Rejected and damaged quantities use product-specific units and are shown per receipt line.']]);
    }

    public function store(Request $request, GoodsReceiptService $receipts, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = $this->tenantId(); $data = $this->validated($request); $order = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($data['purchase_order_id']); $this->assertOrderScope($request, $authorization, $tenantId, $order);
        $receipt = $receipts->createDraft($tenantId, $request->user()->id, $order, [...$data, 'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid()]); $audit->record($request, 'goods_receipt.created', $tenantId, $receipt, ['purchase_order_id' => $order->id]);
        return response()->json(['data' => $receipt->load(['purchaseOrder.supplier', 'company', 'branch', 'warehouse', 'items'])], 201);
    }

    public function show(Request $request, GoodsReceipt $goodsReceipt, AuthorizationService $authorization): JsonResponse
    {
        $this->assertReceiptAccess($request, $authorization, $goodsReceipt);
        return response()->json(['data' => $goodsReceipt->load(['purchaseOrder.supplier', 'purchaseOrder.company', 'purchaseOrder.branch', 'company', 'branch', 'warehouse', 'receivedBy', 'postedBy', 'items.purchaseOrderItem.variant.product', 'items.location'])]);
    }

    public function update(Request $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $receipts, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertReceiptAccess($request, $authorization, $goodsReceipt); $data = $this->validated($request, false);
        $updated = $receipts->updateDraft($this->tenantId(), $request->user()->id, $goodsReceipt, $data); $audit->record($request, 'goods_receipt.updated', $this->tenantId(), $updated);
        return response()->json(['data' => $updated]);
    }

    public function post(Request $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $receipts, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertReceiptAccess($request, $authorization, $goodsReceipt, true); $posted = $receipts->postReceipt($this->tenantId(), $request->user()->id, $goodsReceipt); $audit->record($request, 'goods_receipt.posted', $this->tenantId(), $posted, ['purchase_order_id' => $posted->purchase_order_id]);
        return response()->json(['data' => $posted], 200);
    }

    /** Compatibility endpoint retained for clients from the pre-Phase-4 API. */
    public function legacyStore(Request $request, PurchaseOrder $purchaseOrder, GoodsReceiptService $receipts, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = $this->tenantId(); abort_unless((string) $purchaseOrder->tenant_id === $tenantId, 404); $this->assertOrderScope($request, $authorization, $tenantId, $purchaseOrder);
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.purchase_order_item_id' => ['required', 'uuid'], 'lines.*.location_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]);
        $receipt = $receipts->post($tenantId, $request->user()->id, $purchaseOrder, $data['lines'], $data['idempotency_key']); $audit->record($request, 'goods_receipt.posted', $tenantId, $receipt, ['purchase_order_id' => $purchaseOrder->id]); return response()->json(['data' => $receipt], 201);
    }

    public function movements(Request $request, GoodsReceipt $goodsReceipt, AuthorizationService $authorization): JsonResponse
    {
        $this->assertReceiptAccess($request, $authorization, $goodsReceipt); return response()->json(['data' => StockMovement::where('tenant_id', $this->tenantId())->where('reference_type', GoodsReceipt::class)->where('reference_id', $goodsReceipt->id)->with(['location', 'variant.product'])->latest('posted_at')->get()]);
    }

    public function print(Request $request, GoodsReceipt $goodsReceipt, AuthorizationService $authorization)
    {
        $this->assertReceiptAccess($request, $authorization, $goodsReceipt);
        return response()->view('procurement.goods-receipt', ['receipt' => $goodsReceipt->load(['purchaseOrder.supplier', 'company', 'branch', 'warehouse', 'items.purchaseOrderItem.variant.product', 'items.location'])]);
    }

    public function receivingSummary(Request $request, PurchaseOrder $purchaseOrder, AuthorizationService $authorization): JsonResponse
    {
        $this->assertOrderScope($request, $authorization, $this->tenantId(), $purchaseOrder); $items = $purchaseOrder->items()->with('variant.product')->get(); return response()->json(['data' => ['purchase_order_id' => $purchaseOrder->id, 'state' => $purchaseOrder->state, 'items' => $items->map(fn ($item) => ['id' => $item->id, 'variant_id' => $item->variant_id, 'product' => $item->variant?->product?->name, 'sku' => $item->variant?->sku, 'ordered_quantity' => (string) $item->ordered_quantity, 'received_quantity' => (string) $item->received_quantity, 'remaining_quantity' => Decimal::sub((string) $item->ordered_quantity, (string) $item->received_quantity)])->values()]]);
    }

    public function receivablePurchaseOrders(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenantId(); $companyId = $request->string('company_id')->toString() ?: null; $branchId = $request->string('branch_id')->toString() ?: null; $this->assertScope($request, $authorization, $tenantId, $companyId, $branchId); $accessible = $companyId ? null : $authorization->accessibleCompanyIds($request->user(), $tenantId, 'procurement.manage'); if ($accessible === null) $accessible = CompanyMembership::where('tenant_id', $tenantId)->where('user_id', $request->user()->id)->where('status', 'active')->pluck('company_id')->all(); $orders = PurchaseOrder::where('tenant_id', $tenantId)->whereIn('state', ['approved', 'partially_received'])->when(is_array($accessible), fn ($q) => $q->whereIn('company_id', $accessible))->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->with(['supplier', 'items.variant.product'])->orderByDesc('order_date')->limit(100)->get(); return response()->json(['data' => $orders]);
    }

    private function validated(Request $request, bool $requiredOrder = true): array
    {
        return $request->validate(['purchase_order_id' => [$requiredOrder ? 'required' : 'sometimes', 'uuid'], 'company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'warehouse_id' => ['required', 'uuid'], 'receipt_date' => ['nullable', 'date'], 'supplier_delivery_number' => ['nullable', 'string', 'max:120'], 'reference_number' => ['nullable', 'string', 'max:120'], 'receiving_notes' => ['nullable', 'string', 'max:5000'], 'idempotency_key' => ['nullable', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.purchase_order_item_id' => ['required', 'uuid'], 'lines.*.location_id' => ['required', 'uuid'], 'lines.*.quantity' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/'], 'lines.*.accepted_quantity' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/'], 'lines.*.rejected_quantity' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/'], 'lines.*.damaged_quantity' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/'], 'lines.*.inspection_status' => ['nullable', 'in:accepted,inspected,rejected,damaged,quarantined'], 'lines.*.inspection_notes' => ['nullable', 'string', 'max:2000'], 'lines.*.rejection_reason' => ['nullable', 'string', 'max:1000'], 'lines.*.damage_reason' => ['nullable', 'string', 'max:1000'], 'lines.*.batch_number' => ['nullable', 'string', 'max:120'], 'lines.*.serial_number' => ['nullable', 'string', 'max:120'], 'lines.*.manufacturing_date' => ['nullable', 'date'], 'lines.*.expiry_date' => ['nullable', 'date', 'after_or_equal:manufacturing_date']]);
    }

    private function assertReceiptAccess(Request $request, AuthorizationService $authorization, GoodsReceipt $receipt, bool $post = false): void
    {
        $tenantId = $this->tenantId(); abort_unless((string) $receipt->tenant_id === $tenantId, 404); $permission = $post ? 'inventory.manage' : 'procurement.manage'; abort_unless($authorization->allows($request->user(), $tenantId, $permission, $receipt->company_id, $receipt->branch_id), 403); if ($receipt->company_id) abort_unless(CompanyMembership::where('tenant_id', $tenantId)->where('company_id', $receipt->company_id)->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 403);
    }

    private function assertOrderScope(Request $request, AuthorizationService $authorization, string $tenantId, PurchaseOrder $order): void
    {
        abort_unless((string) $order->tenant_id === $tenantId, 404); abort_unless($order->company_id && $authorization->allows($request->user(), $tenantId, 'procurement.manage', $order->company_id, $order->branch_id), 403); abort_unless(CompanyMembership::where('tenant_id', $tenantId)->where('company_id', $order->company_id)->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 403);
    }

    private function assertScope(Request $request, AuthorizationService $authorization, string $tenantId, ?string $companyId, ?string $branchId): void
    {
        if ($companyId) { abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage', $companyId, $branchId) || $authorization->allows($request->user(), $tenantId, 'inventory.manage', $companyId, $branchId), 403); abort_unless(CompanyMembership::where('tenant_id', $tenantId)->where('company_id', $companyId)->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 403); }
    }

    private function tenantId(): string { return (string) app(TenantContext::class)->id(); }
}
