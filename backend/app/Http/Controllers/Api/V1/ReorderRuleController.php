<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\InventoryAlert;
use App\Models\ReorderRule;
use App\Models\ProductVariant;
use App\Models\WarehouseLocation;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReorderRuleController
{
    public function index(): JsonResponse { $tenantId = app(TenantContext::class)->id(); return response()->json(['data' => ReorderRule::where('tenant_id', $tenantId)->with(['variant', 'location'])->paginate(25), 'alerts' => InventoryAlert::where('tenant_id', $tenantId)->whereNull('resolved_at')->with(['variant', 'location'])->get()]); }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['variant_id' => ['required', 'uuid'], 'location_id' => ['required', 'uuid'], 'reorder_point' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'reorder_quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(ProductVariant::whereKey($data['variant_id'])->where('tenant_id', $tenantId)->exists() && WarehouseLocation::whereKey($data['location_id'])->where('tenant_id', $tenantId)->exists(), 404); $rule = ReorderRule::updateOrCreate(['variant_id' => $data['variant_id'], 'location_id' => $data['location_id']], [...$data, 'tenant_id' => $tenantId]); $audit->record($request, 'inventory.reorder_rule.updated', $tenantId, $rule); return response()->json(['data' => $rule]); }
}
