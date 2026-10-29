<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseOrder;
use App\Models\WarehouseLocation;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\GoodsReceiptService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GoodsReceiptController
{
    public function store(Request $request, PurchaseOrder $purchaseOrder, GoodsReceiptService $receipts, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($purchaseOrder->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.purchase_order_item_id' => ['required', 'uuid'], 'lines.*.location_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); foreach ($data['lines'] as $line) abort_unless(WarehouseLocation::whereKey($line['location_id'])->where('tenant_id', $tenantId)->exists(), 404); $receipt = $receipts->post($tenantId, $request->user()->id, $purchaseOrder, $data['lines'], $data['idempotency_key']); $audit->record($request, 'goods_receipt.posted', $tenantId, $receipt, ['purchase_order_id' => $purchaseOrder->id]); return response()->json(['data' => $receipt], 201); }
}
