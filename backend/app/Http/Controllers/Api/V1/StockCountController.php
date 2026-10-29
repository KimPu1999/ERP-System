<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\InventoryBalance;
use App\Models\ProductVariant;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\WarehouseLocation;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\StockMovementService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StockCountController
{
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['location_id' => ['required', 'uuid'], 'idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.counted_quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(WarehouseLocation::whereKey($data['location_id'])->where('tenant_id', $tenantId)->exists(), 404); foreach ($data['lines'] as $line) abort_unless(ProductVariant::whereKey($line['variant_id'])->where('tenant_id', $tenantId)->exists(), 404); $count = DB::transaction(function () use ($data, $tenantId, $request): StockCount { $count = StockCount::create(['tenant_id' => $tenantId, 'location_id' => $data['location_id'], 'created_by' => $request->user()->id, 'status' => 'draft', 'idempotency_key' => $data['idempotency_key']]); foreach ($data['lines'] as $line) StockCountLine::create(['tenant_id' => $tenantId, 'count_id' => $count->id, 'variant_id' => $line['variant_id'], 'counted_quantity' => $line['counted_quantity']]); return $count; }); $audit->record($request, 'inventory.stock_count.created', $tenantId, $count); return response()->json(['data' => $count->load('lines')], 201); }
    public function post(Request $request, StockCount $count, StockMovementService $movements, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($count->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $posted = DB::transaction(function () use ($count, $tenantId, $request, $movements): StockCount { $count = StockCount::whereKey($count->id)->lockForUpdate()->with('lines')->firstOrFail(); if ($count->status === 'posted') return $count; foreach ($count->lines as $index => $line) { $balance = InventoryBalance::where('tenant_id', $tenantId)->where('variant_id', $line->variant_id)->where('location_id', $count->location_id)->lockForUpdate()->first(); $current = $balance ? (string) $balance->quantity : '0'; $delta = Decimal::sub((string) $line->counted_quantity, $current); if (Decimal::compare($delta, '0') !== 0) $movements->post(['tenant_id' => $tenantId, 'variant_id' => $line->variant_id, 'location_id' => $count->location_id, 'created_by' => $request->user()->id, 'movement_type' => 'stock_count', 'quantity' => $delta, 'unit_cost' => '0', 'reference_type' => StockCount::class, 'reference_id' => $count->id, 'idempotency_key' => 'count:'.$count->id.':'.$index]); } $count->update(['status' => 'posted', 'posted_at' => now()]); return $count; }); $audit->record($request, 'inventory.stock_count.posted', $tenantId, $posted); return response()->json(['data' => $posted]); }
}
