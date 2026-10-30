<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{InventoryAlert, InventoryBalance, InventorySetting, ProductVariant, ReorderRule, StockMovement, WarehouseLocation};
use App\Services\{AuditLogger, AuthorizationService, StockOperationService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class InventoryController
{
    public function balances(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        [$companyIds, $branchIds] = $this->readScope($request, $authorization, $tenantId);
        $filters = $request->validate($this->listRules());
        $query = InventoryBalance::query()->where('inventory_balances.tenant_id', $tenantId)->with(['variant.product', 'location.warehouse']);
        $this->applyLocationScope($query, $companyIds, $branchIds);
        $this->applyBalanceFilters($query, $filters);
        $sort = $filters['sort'] ?? 'updated_at';
        $direction = $filters['direction'] ?? 'desc';
        $paginator = $query->orderBy('inventory_balances.' . $sort, $direction)->paginate($filters['per_page'] ?? 25);
        $paginator->getCollection()->transform(fn (InventoryBalance $balance) => $this->balancePayload($balance));
        return response()->json(['data' => $paginator]);
    }

    public function movements(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        [$companyIds, $branchIds] = $this->readScope($request, $authorization, $tenantId);
        $data = $request->validate(['variant_id' => ['nullable', 'uuid'], 'warehouse_id' => ['nullable', 'uuid'], 'movement_type' => ['nullable', 'string', 'max:50'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100']]);
        $query = StockMovement::query()->where('stock_movements.tenant_id', $tenantId)->with(['variant.product', 'location.warehouse']);
        $this->applyLocationScope($query, $companyIds, $branchIds, 'stock_movements.location_id');
        $query->when($data['variant_id'] ?? null, fn ($q, $id) => $q->where('variant_id', $id))->when($data['warehouse_id'] ?? null, fn ($q, $id) => $q->whereHas('location', fn ($location) => $location->where('warehouse_id', $id)))->when($data['movement_type'] ?? null, fn ($q, $type) => $q->where('movement_type', $type))->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('posted_at', '>=', $date))->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('posted_at', '<=', $date));
        return response()->json(['data' => $query->orderByDesc('posted_at')->paginate($data['per_page'] ?? 25)]);
    }

    public function report(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        [$companyIds, $branchIds] = $this->readScope($request, $authorization, $tenantId);
        $data = $request->validate(['company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'warehouse_id' => ['nullable', 'uuid']]);
        if (! empty($data['company_id'])) $this->assertCompanyFilter($data['company_id'], $companyIds);
        if (! empty($data['branch_id'])) $this->assertBranchFilter($data['branch_id'], $branchIds);
        $balanceQuery = InventoryBalance::query()->where('inventory_balances.tenant_id', $tenantId)->with(['variant.product', 'location.warehouse']);
        $this->applyLocationScope($balanceQuery, $companyIds, $branchIds);
        $this->applyBalanceFilters($balanceQuery, ['company_id' => $data['company_id'] ?? null, 'branch_id' => $data['branch_id'] ?? null, 'warehouse_id' => $data['warehouse_id'] ?? null]);
        $balances = $balanceQuery->get();
        $lowAlerts = InventoryAlert::query()->where('inventory_alerts.tenant_id', $tenantId)->whereNull('resolved_at')->with(['variant.product', 'location.warehouse'])->whereHas('location', function ($location) use ($companyIds, $branchIds, $data): void { $location->whereHas('warehouse', function ($warehouse) use ($companyIds, $branchIds, $data): void { $warehouse->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', $companyIds))->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))->when($data['warehouse_id'] ?? null, fn ($q, $id) => $q->whereKey($id)); }); })->get();
        $available = $balances->sum(fn (InventoryBalance $balance) => max((float) $balance->quantity - (float) $balance->reserved_quantity, 0));
        $reserved = $balances->sum(fn (InventoryBalance $balance) => (float) $balance->reserved_quantity);
        $onHand = $balances->sum(fn (InventoryBalance $balance) => (float) $balance->quantity);
        return response()->json(['data' => ['total_skus' => $balances->pluck('variant_id')->unique()->count(), 'balance_count' => $balances->count(), 'on_hand_quantity' => number_format($onHand, 6, '.', ''), 'reserved_quantity' => number_format($reserved, 6, '.', ''), 'available_quantity' => number_format($available, 6, '.', ''), 'low_stock_alerts' => $lowAlerts->values(), 'out_of_stock_count' => $balances->filter(fn (InventoryBalance $balance) => (float) $balance->quantity - (float) $balance->reserved_quantity <= 0)->count()]]);
    }

    public function settings(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['allow_negative_stock' => ['required', 'boolean']]); $setting = InventorySetting::updateOrCreate(['tenant_id' => $tenantId], $data); $audit->record($request, 'inventory.settings.updated', $tenantId, $setting); return response()->json(['data' => $setting]);
    }

    public function adjustment(Request $request, StockOperationService $operations, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.location_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^-?\d+(\.\d{1,6})?$/'], 'lines.*.unit_cost' => ['nullable', 'regex:/^\d+(\.\d{1,6})?$/']]); $this->assertLines($tenantId, $data['lines'], $request, $authorization); $adjustment = $operations->adjustment($tenantId, $request->user()->id, $data['lines'], $data['reason'], $data['idempotency_key']); $audit->record($request, 'inventory.adjustment.posted', $tenantId, $adjustment); return response()->json(['data' => $adjustment], 201);
    }

    public function transfer(Request $request, StockOperationService $operations, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage'), 403); $data = $request->validate(['from_location_id' => ['required', 'uuid'], 'to_location_id' => ['required', 'uuid'], 'reason' => ['nullable', 'string', 'max:500'], 'idempotency_key' => ['required', 'string', 'max:100'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); $from = WarehouseLocation::where('tenant_id', $tenantId)->with('warehouse')->findOrFail($data['from_location_id']); $to = WarehouseLocation::where('tenant_id', $tenantId)->with('warehouse')->findOrFail($data['to_location_id']); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage', $from->warehouse->company_id, $from->warehouse->branch_id) && $authorization->allows($request->user(), $tenantId, 'inventory.manage', $to->warehouse->company_id, $to->warehouse->branch_id), 403); $this->assertVariants($tenantId, $data['lines']); $transfer = $operations->transfer($tenantId, $request->user()->id, $from->id, $to->id, $data['lines'], $data['reason'] ?? null, $data['idempotency_key']); $audit->record($request, 'inventory.transfer.posted', $tenantId, $transfer); return response()->json(['data' => $transfer], 201);
    }

    public function export(Request $request, AuthorizationService $authorization): Response
    {
        $tenantId = app(TenantContext::class)->id(); [$companyIds, $branchIds] = $this->readScope($request, $authorization, $tenantId); $query = InventoryBalance::query()->where('tenant_id', $tenantId)->with(['variant', 'location.warehouse']); $this->applyLocationScope($query, $companyIds, $branchIds); $balances = $query->cursor(); return response()->streamDownload(function () use ($balances): void { $out = fopen('php://output', 'w'); fputcsv($out, ['variant_id', 'sku', 'warehouse', 'location_id', 'quantity', 'reserved_quantity', 'available_quantity']); foreach ($balances as $balance) fputcsv($out, [$balance->variant_id, $balance->variant?->sku, $balance->location?->warehouse?->name, $balance->location_id, $balance->quantity, $balance->reserved_quantity, Decimal::sub((string) $balance->quantity, (string) $balance->reserved_quantity)]); fclose($out); }, 'inventory.csv', ['Content-Type' => 'text/csv']);
    }

    private function listRules(): array { return ['company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'warehouse_id' => ['nullable', 'uuid'], 'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['available', 'low_stock', 'out_of_stock'])], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100'], 'sort' => ['nullable', Rule::in(['quantity', 'reserved_quantity', 'updated_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]; }

    private function readScope(Request $request, AuthorizationService $authorization, string $tenantId): array
    {
        $companyIds = []; $branchIds = []; $allCompanies = false; $allBranches = false; $hasPermission = false;
        foreach (['inventory.manage', 'reports.view'] as $permission) { $companies = $authorization->accessibleCompanyIds($request->user(), $tenantId, $permission); $branches = $authorization->accessibleBranchIds($request->user(), $tenantId, $permission); if ($companies === null) $allCompanies = true; elseif ($companies !== []) $companyIds = [...$companyIds, ...$companies]; if ($branches === null) $allBranches = true; elseif ($branches !== []) $branchIds = [...$branchIds, ...$branches]; if ($companies === null || $companies !== []) $hasPermission = true; }
        abort_unless($hasPermission, 403);
        // An assignment scoped to a company but not a branch returns an empty
        // branch list from AuthorizationService. Empty means all branches in
        // the already-authorized company scope; only a non-empty list is a
        // branch restriction.
        return [$allCompanies ? null : array_values(array_unique($companyIds)), $allBranches || $branchIds === [] ? null : array_values(array_unique($branchIds))];
    }

    private function applyLocationScope(Builder $query, ?array $companyIds, ?array $branchIds, string $column = 'inventory_balances.location_id'): void
    {
        $query->whereHas('location', function ($location) use ($companyIds, $branchIds): void { $location->whereHas('warehouse', function ($warehouse) use ($companyIds, $branchIds): void { $warehouse->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', $companyIds))->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds)); }); });
    }

    private function applyBalanceFilters(Builder $query, array $data): void
    {
        $query->when($data['company_id'] ?? null, fn ($q, $id) => $q->whereHas('location.warehouse', fn ($warehouse) => $warehouse->where('company_id', $id)))->when($data['branch_id'] ?? null, fn ($q, $id) => $q->whereHas('location.warehouse', fn ($warehouse) => $warehouse->where('branch_id', $id)))->when($data['warehouse_id'] ?? null, fn ($q, $id) => $q->whereHas('location', fn ($location) => $location->where('warehouse_id', $id)))->when($data['search'] ?? null, function ($q, $value): void { $term = '%' . addcslashes($value, '%_') . '%'; $q->whereHas('variant', fn ($variant) => $variant->where('sku', 'ilike', $term)->orWhereHas('product', fn ($product) => $product->where('name', 'ilike', $term))); })->when(($data['status'] ?? null) === 'out_of_stock', fn ($q) => $q->whereRaw('(quantity - reserved_quantity) <= 0'))->when(($data['status'] ?? null) === 'available', fn ($q) => $q->whereRaw('(quantity - reserved_quantity) > 0'))->when(($data['status'] ?? null) === 'low_stock', fn ($q) => $q->whereRaw('(quantity - reserved_quantity) > 0')->whereExists(fn ($sub) => $sub->select(DB::raw('1'))->from('reorder_rules')->whereColumn('reorder_rules.variant_id', 'inventory_balances.variant_id')->whereColumn('reorder_rules.location_id', 'inventory_balances.location_id')->whereColumn('reorder_rules.reorder_point', '>=', DB::raw('(inventory_balances.quantity - inventory_balances.reserved_quantity)'))));
    }

    private function balancePayload(InventoryBalance $balance): array
    {
        $available = Decimal::sub((string) $balance->quantity, (string) $balance->reserved_quantity); $status = Decimal::compare($available, '0') <= 0 ? 'out_of_stock' : 'available'; $rule = ReorderRule::where('tenant_id', $balance->tenant_id)->where('variant_id', $balance->variant_id)->where('location_id', $balance->location_id)->first(); if ($rule && Decimal::compare($available, (string) $rule->reorder_point) <= 0) $status = 'low_stock'; return ['id' => $balance->id, 'variant_id' => $balance->variant_id, 'location_id' => $balance->location_id, 'quantity' => (string) $balance->quantity, 'reserved_quantity' => (string) $balance->reserved_quantity, 'available_quantity' => $available, 'status' => $status, 'reorder_point' => $rule?->reorder_point, 'variant' => $balance->variant, 'location' => $balance->location];
    }

    private function assertCompanyFilter(string $companyId, ?array $companyIds): void { abort_unless($companyIds === null || in_array($companyId, $companyIds, true), 403); }
    private function assertBranchFilter(string $branchId, ?array $branchIds): void { abort_unless($branchIds === null || in_array($branchId, $branchIds, true), 403); }
    private function assertVariants(string $tenantId, array $lines): void { foreach ($lines as $line) abort_unless(ProductVariant::whereKey($line['variant_id'])->where('tenant_id', $tenantId)->exists(), 404); }
    private function assertLines(string $tenantId, array $lines, Request $request, AuthorizationService $authorization): void { $this->assertVariants($tenantId, $lines); foreach ($lines as $line) { $location = WarehouseLocation::whereKey($line['location_id'])->where('tenant_id', $tenantId)->with('warehouse')->firstOrFail(); abort_unless($authorization->allows($request->user(), $tenantId, 'inventory.manage', $location->warehouse->company_id, $location->warehouse->branch_id), 403); } }
}
