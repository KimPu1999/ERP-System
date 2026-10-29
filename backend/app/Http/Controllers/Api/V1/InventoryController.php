<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\InventoryAlert;
use App\Models\InventoryBalance;
use App\Models\InventorySetting;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\WarehouseLocation;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\StockOperationService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class InventoryController
{
    public function balances(Request $request): JsonResponse { $query = InventoryBalance::query()->where('tenant_id', app(TenantContext::class)->id())->with(['variant', 'location'])->when($request->filled('variant_id'), fn ($query) => $query->where('variant_id', $request->string('variant_id')))->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->string('location_id'))); return response()->json(['data' => $query->paginate(min($request->integer('per_page', 25), 100))]); }
    public function movements(Request $request): JsonResponse { return response()->json(['data' => StockMovement::where('tenant_id', app(TenantContext::class)->id())->when($request->filled('variant_id'), fn ($query) => $query->where('variant_id', $request->string('variant_id')))->orderByDesc('posted_at')->paginate(min($request->integer('per_page', 25), 100))]); }
    public function report(): JsonResponse { $tenantId = app(TenantContext::class)->id(); return response()->json(['data' => ['low_stock_alerts' => InventoryAlert::where('tenant_id', $tenantId)->whereNull('resolved_at')->with(['variant', 'location'])->get(), 'total_skus' => ProductVariant::where('tenant_id', $tenantId)->count(), 'balances' => InventoryBalance::where('tenant_id', $tenantId)->sum('quantity')]]); }
    public function settings(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['allow_negative_stock' => ['required', 'boolean']]); $setting = InventorySetting::updateOrCreate(['tenant_id' => $tenantId], $data); $audit->record($request, 'inventory.settings.updated', $tenantId, $setting); return response()->json(['data' => $setting]); }
    public function adjustment(Request $request, StockOperationService $operations, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.location_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^-?\d+(\.\d{1,6})?$/'], 'lines.*.unit_cost' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/']]); $this->assertLines($tenantId, $data['lines']); $adjustment = $operations->adjustment($tenantId, $request->user()->id, $data['lines'], $data['reason'], $data['idempotency_key']); $audit->record($request, 'inventory.adjustment.posted', $tenantId, $adjustment); return response()->json(['data' => $adjustment], 201); }
    public function transfer(Request $request, StockOperationService $operations, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['from_location_id' => ['required', 'uuid'], 'to_location_id' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:500'], 'idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(WarehouseLocation::whereKey($data['from_location_id'])->where('tenant_id', $tenantId)->exists() && WarehouseLocation::whereKey($data['to_location_id'])->where('tenant_id', $tenantId)->exists(), 404); $this->assertVariants($tenantId, $data['lines']); $transfer = $operations->transfer($tenantId, $request->user()->id, $data['from_location_id'], $data['to_location_id'], $data['lines'], $data['reason'] ?? null, $data['idempotency_key']); $audit->record($request, 'inventory.transfer.posted', $tenantId, $transfer); return response()->json(['data' => $transfer], 201); }
    public function export(Request $request): Response { $tenantId = app(TenantContext::class)->id(); $balances = InventoryBalance::where('tenant_id', $tenantId)->with(['variant', 'location'])->cursor(); return response()->streamDownload(function () use ($balances): void { $out = fopen('php://output', 'w'); fputcsv($out, ['variant_id', 'sku', 'location_id', 'quantity', 'reserved_quantity']); foreach ($balances as $balance) fputcsv($out, [$balance->variant_id, $balance->variant?->sku, $balance->location_id, $balance->quantity, $balance->reserved_quantity]); fclose($out); }, 'inventory.csv', ['Content-Type' => 'text/csv']); }
    private function assertVariants(string $tenantId, array $lines): void { foreach ($lines as $line) abort_unless(ProductVariant::whereKey($line['variant_id'])->where('tenant_id', $tenantId)->exists(), 404); }
    private function assertLines(string $tenantId, array $lines): void { $this->assertVariants($tenantId, $lines); foreach ($lines as $line) abort_unless(WarehouseLocation::whereKey($line['location_id'])->where('tenant_id', $tenantId)->exists(), 404); }
}
