<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\WarehouseLocation;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\ProcurementNumberService;
use App\Services\StockMovementService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PurchaseReturnController
{
    public function post(Request $request, PurchaseOrder $purchaseOrder, StockMovementService $movements, ProcurementNumberService $numbers, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($purchaseOrder->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.purchase_order_item_id' => ['required', 'uuid'], 'lines.*.location_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(WarehouseLocation::whereKey($data['lines'][0]['location_id'])->where('tenant_id', $tenantId)->exists(), 404); $return = DB::transaction(function () use ($data, $tenantId, $request, $purchaseOrder, $movements, $numbers): PurchaseReturn { $existing = PurchaseReturn::where('tenant_id', $tenantId)->where('idempotency_key', $data['idempotency_key'])->first(); if ($existing) return $existing; $return = PurchaseReturn::create(['tenant_id' => $tenantId, 'purchase_order_id' => $purchaseOrder->id, 'created_by' => $request->user()->id, 'number' => $numbers->make('RET'), 'state' => 'posted', 'idempotency_key' => $data['idempotency_key']]); foreach ($data['lines'] as $index => $line) { $item = PurchaseOrderItem::where('tenant_id', $tenantId)->where('purchase_order_id', $purchaseOrder->id)->whereKey($line['purchase_order_item_id'])->lockForUpdate()->firstOrFail(); $quantity = Decimal::normalize((string) $line['quantity']); $available = Decimal::sub((string) $item->received_quantity, (string) $item->returned_quantity); if (Decimal::compare($quantity, '0') <= 0 || Decimal::compare($quantity, $available) > 0) abort(422, 'Return quantity exceeds received quantity.'); PurchaseReturnItem::create(['tenant_id' => $tenantId, 'purchase_return_id' => $return->id, 'purchase_order_item_id' => $item->id, 'variant_id' => $item->variant_id, 'location_id' => $line['location_id'], 'quantity' => $quantity]); $movements->post(['tenant_id' => $tenantId, 'variant_id' => $item->variant_id, 'location_id' => $line['location_id'], 'created_by' => $request->user()->id, 'movement_type' => 'purchase_return', 'quantity' => '-'.$quantity, 'unit_cost' => $item->unit_price, 'reference_type' => PurchaseReturn::class, 'reference_id' => $return->id, 'idempotency_key' => 'purchase-return:'.$return->id.':'.$index]); $item->update(['returned_quantity' => Decimal::add((string) $item->returned_quantity, $quantity)]); } return $return; }); $audit->record($request, 'purchase_return.posted', $tenantId, $return); return response()->json(['data' => $return], 201); }
}
