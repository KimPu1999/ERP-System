<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WarehouseController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $companyId = $request->string('company_id')->toString() ?: null;
        $branchId = $request->string('branch_id')->toString() ?: null;
        if ($companyId) abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage', $companyId, $branchId) || $authorization->allows($request->user(), $tenantId, 'inventory.manage', $companyId, $branchId), 403);
        return response()->json(['data' => Warehouse::where('tenant_id', $tenantId)->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('is_active', true)->orderBy('name')->paginate(100)]);
    }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40'], 'company_id' => ['required', 'uuid'], 'branch_id' => ['nullable', 'uuid']]); abort_unless(Company::whereKey($data['company_id'])->where('tenant_id', $tenantId)->exists(), 404); if (! empty($data['branch_id'])) abort_unless(Branch::whereKey($data['branch_id'])->where('tenant_id', $tenantId)->where('company_id', $data['company_id'])->exists(), 404); $warehouse = Warehouse::create([...$data, 'tenant_id' => $tenantId]); $audit->record($request, 'warehouse.created', $tenantId, $warehouse); return response()->json(['data' => $warehouse], 201); }
    public function locationStore(Request $request, Warehouse $warehouse, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($warehouse->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage', $warehouse->company_id), 403); $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40'], 'parent_id' => ['nullable', 'uuid']]); if (! empty($data['parent_id'])) abort_unless(WarehouseLocation::whereKey($data['parent_id'])->where('tenant_id', $tenantId)->where('warehouse_id', $warehouse->id)->exists(), 404); $location = WarehouseLocation::create([...$data, 'tenant_id' => $tenantId, 'warehouse_id' => $warehouse->id]); $audit->record($request, 'warehouse.location.created', $tenantId, $location); return response()->json(['data' => $location], 201); }
}
