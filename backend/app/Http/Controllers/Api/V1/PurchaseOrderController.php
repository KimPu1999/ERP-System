<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PurchaseOrderApproved;
use App\Models\ProcurementApproval;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\ProcurementNumberService;
use App\Services\ProcurementStateService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderController
{
    public function index(): JsonResponse { return response()->json(['data' => PurchaseOrder::where('tenant_id', app(TenantContext::class)->id())->with(['items', 'supplier'])->orderByDesc('created_at')->paginate(25)]); }
    public function store(Request $request, ProcurementNumberService $numbers, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); $data = $request->validate(['supplier_id' => ['required', 'uuid'], 'expected_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.variant_id' => ['required', 'uuid'], 'items.*.ordered_quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'items.*.unit_price' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(Supplier::whereKey($data['supplier_id'])->where('tenant_id', $tenantId)->exists(), 404); foreach ($data['items'] as $item) abort_unless(ProductVariant::whereKey($item['variant_id'])->where('tenant_id', $tenantId)->exists(), 404); $order = DB::transaction(function () use ($data, $tenantId, $request, $numbers): PurchaseOrder { $model = PurchaseOrder::create(['tenant_id' => $tenantId, 'supplier_id' => $data['supplier_id'], 'created_by' => $request->user()->id, 'number' => $numbers->make('PO'), 'state' => 'draft', 'expected_at' => $data['expected_at'] ?? null, 'notes' => $data['notes'] ?? null]); foreach ($data['items'] as $item) PurchaseOrderItem::create(['tenant_id' => $tenantId, 'purchase_order_id' => $model->id, 'variant_id' => $item['variant_id'], 'ordered_quantity' => $item['ordered_quantity'], 'received_quantity' => '0', 'unit_price' => $item['unit_price']]); return $model; }); $audit->record($request, 'purchase_order.created', $tenantId, $order); return response()->json(['data' => $order->load('items')], 201); }
    public function submit(Request $request, PurchaseOrder $purchaseOrder, ProcurementStateService $states, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($purchaseOrder->tenant_id === $tenantId, 404); $approvers = $request->validate(['approver_ids' => ['array'], 'approver_ids.*' => ['uuid', 'exists:users,id']])['approver_ids'] ?? [$request->user()->id]; $order = DB::transaction(function () use ($purchaseOrder, $states, $request, $tenantId, $approvers): PurchaseOrder { $model = $states->transition($purchaseOrder, 'submitted', $request, $tenantId); foreach (array_values(array_unique($approvers)) as $level => $approverId) ProcurementApproval::create(['tenant_id' => $tenantId, 'document_type' => PurchaseOrder::class, 'document_id' => $model->id, 'approver_id' => $approverId, 'level' => $level + 1, 'state' => 'pending']); return $model; }); $audit->record($request, 'purchase_order.submitted', $tenantId, $order); return response()->json(['data' => $order]); }
    public function approve(Request $request, PurchaseOrder $purchaseOrder, ProcurementStateService $states, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($purchaseOrder->tenant_id === $tenantId, 404); $approval = ProcurementApproval::where('tenant_id', $tenantId)->where('document_type', PurchaseOrder::class)->where('document_id', $purchaseOrder->id)->where('approver_id', $request->user()->id)->where('state', 'pending')->orderBy('level')->lockForUpdate()->firstOrFail(); $approval->update(['state' => 'approved', 'comment' => $request->string('comment')->toString(), 'decided_at' => now()]); $pending = ProcurementApproval::where('tenant_id', $tenantId)->where('document_type', PurchaseOrder::class)->where('document_id', $purchaseOrder->id)->where('state', 'pending')->exists(); $order = $purchaseOrder; if (! $pending) { $order = $states->transition($purchaseOrder, 'approved', $request, $tenantId); event(new PurchaseOrderApproved($order)); } $audit->record($request, 'purchase_order.approval.recorded', $tenantId, $order); return response()->json(['data' => $order->fresh()]); }
}
