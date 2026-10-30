<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AssetCategory, AssetTransaction, Company, FixedAsset};
use App\Services\{AuthorizationService, OptionalModuleService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FixedAssetController
{
    public function categories(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); [$tenant, $companies] = $this->scope($request, $authorization); $query = AssetCategory::where('tenant_id', $tenant)->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')))->orderBy('name'); return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 50), 1), 100))]);
    }

    public function summary(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); [$tenant, $companies] = $this->scope($request, $authorization);
        $query = FixedAsset::where('tenant_id', $tenant)->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')));
        $assets = (clone $query)->get(['state', 'acquisition_cost', 'accumulated_depreciation']);
        $active = $assets->whereIn('state', ['capitalized', 'active', 'under_maintenance'])->count();
        return response()->json(['data' => [
            'total_assets' => $assets->count(), 'active_assets' => $active,
            'acquisition_cost' => $assets->sum('acquisition_cost'), 'accumulated_depreciation' => $assets->sum('accumulated_depreciation'),
            'net_book_value' => $assets->sum(fn ($asset) => Decimal::sub((string) $asset->acquisition_cost, (string) $asset->accumulated_depreciation)),
            'under_maintenance' => $assets->where('state', 'under_maintenance')->count(), 'pending_disposal' => $assets->where('state', 'pending_disposal')->count(),
        ]]);
    }

    public function storeCategory(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $tenant = app(TenantContext::class)->id();
        $data = $request->validate(['company_id' => 'required|uuid', 'code' => 'required|string|max:50', 'name' => 'required|string|max:150', 'asset_account_id' => 'nullable|uuid', 'depreciation_account_id' => 'nullable|uuid']);
        abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $data['company_id']), 403);
        abort_unless(Company::where('tenant_id', $tenant)->whereKey($data['company_id'])->exists(), 404);
        abort_if(AssetCategory::where('company_id', $data['company_id'])->where('code', $data['code'])->exists(), 409, 'Asset category code already exists.');
        return response()->json(['data' => AssetCategory::create([...$data, 'tenant_id' => $tenant])], 201);
    }

    public function assets(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); [$tenant, $companies] = $this->scope($request, $authorization); $query = FixedAsset::where('tenant_id', $tenant)->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')))->with('transactions')->orderBy('asset_number'); return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function acquire(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $tenant = app(TenantContext::class)->id(); $data = $request->validate(['company_id' => 'required|uuid', 'category_id' => 'required|uuid', 'asset_number' => 'required|alpha_dash|max:50', 'name' => 'required|string|max:200', 'acquired_on' => 'required|date', 'capitalized_on' => 'nullable|date', 'acquisition_cost' => 'required|numeric|gt:0', 'residual_value' => 'nullable|numeric|min:0', 'useful_life_months' => 'required|integer|min:1', 'depreciation_method' => 'required|in:straight_line']);
        abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $data['company_id']), 403); abort_unless(Company::where('tenant_id', $tenant)->whereKey($data['company_id'])->exists(), 404); abort_unless(AssetCategory::where('tenant_id', $tenant)->where('company_id', $data['company_id'])->whereKey($data['category_id'])->exists(), 404);
        $asset = DB::transaction(function () use ($data, $tenant, $request) { $asset = FixedAsset::create([...$data, 'tenant_id' => $tenant, 'state' => 'draft', 'capitalized_on' => null, 'accumulated_depreciation' => '0']); AssetTransaction::create(['tenant_id' => $tenant, 'asset_id' => $asset->id, 'type' => 'acquisition', 'transaction_date' => $data['acquired_on'], 'amount' => $data['acquisition_cost'], 'created_by' => $request->user()->id, 'description' => 'Asset acquisition registered']); return $asset; });
        return response()->json(['data' => $asset->load('category', 'transactions')], 201);
    }

    public function show(Request $request, FixedAsset $asset, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $this->authorizeAsset($request, $asset, $authorization); return response()->json(['data' => $asset->load('category', 'transactions')]);
    }

    public function update(Request $request, FixedAsset $asset, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $this->authorizeAsset($request, $asset, $authorization);
        abort_if(in_array($asset->state, ['disposed', 'retired'], true), 422, 'Disposed assets cannot be edited.');
        $data = $request->validate(['category_id' => 'sometimes|required|uuid', 'name' => 'sometimes|required|string|max:200', 'residual_value' => 'sometimes|numeric|min:0', 'useful_life_months' => 'sometimes|integer|min:1', 'depreciation_method' => 'sometimes|in:straight_line', 'state' => 'sometimes|in:draft,capitalized,active,under_maintenance,pending_disposal']);
        if (isset($data['category_id'])) abort_unless(AssetCategory::where('tenant_id', $asset->tenant_id)->where('company_id', $asset->company_id)->whereKey($data['category_id'])->exists(), 422, 'Category is not available for this company.');
        $asset->update($data); return response()->json(['data' => $asset->fresh('category', 'transactions')]);
    }

    public function capitalize(Request $request, FixedAsset $asset, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $this->authorizeAsset($request, $asset, $authorization); abort_if($asset->state !== 'draft', 422, 'Only draft assets can be capitalized.');
        $data = $request->validate(['capitalized_on' => 'required|date']);
        $asset = DB::transaction(function () use ($asset, $data, $request) { $locked = FixedAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail(); abort_if($locked->state !== 'draft', 409, 'Asset capitalization was already completed.'); $locked->update(['state' => 'capitalized', 'capitalized_on' => $data['capitalized_on']]); AssetTransaction::create(['tenant_id' => $locked->tenant_id, 'asset_id' => $locked->id, 'type' => 'capitalization', 'transaction_date' => $data['capitalized_on'], 'amount' => $locked->acquisition_cost, 'created_by' => $request->user()->id, 'description' => 'Asset capitalized']); return $locked; });
        event(new \App\Events\FixedAssetCapitalized($asset->tenant_id, $asset->id, $asset->company_id, (string) $asset->acquisition_cost)); return response()->json(['data' => $asset->load('category', 'transactions')]);
    }

    public function depreciate(Request $request, FixedAsset $asset, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $tenant = app(TenantContext::class)->id(); abort_unless($asset->tenant_id === $tenant, 404); abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $asset->company_id), 403); $data = $request->validate(['transaction_date' => 'required|date']);
        $transaction = DB::transaction(function () use ($asset, $data, $request, $tenant) {
            $locked = FixedAsset::whereKey($asset->id)->where('tenant_id', $tenant)->lockForUpdate()->firstOrFail(); abort_if(!in_array($locked->state, ['capitalized', 'active', 'under_maintenance'], true), 422, 'Only capitalized assets can be depreciated.'); if (AssetTransaction::where('asset_id', $locked->id)->where('type', 'depreciation')->whereDate('transaction_date', $data['transaction_date'])->exists()) throw ValidationException::withMessages(['transaction_date' => ['Depreciation has already been recorded for this asset and period.']]);
            $remaining = Decimal::sub((string) $locked->acquisition_cost, (string) $locked->residual_value); $monthly = Decimal::div($remaining, (string) $locked->useful_life_months); $next = Decimal::add((string) $locked->accumulated_depreciation, $monthly); if (Decimal::compare($next, $remaining) > 0) $monthly = Decimal::sub($remaining, (string) $locked->accumulated_depreciation); if (Decimal::compare($monthly, '0') <= 0) throw ValidationException::withMessages(['asset' => ['The asset is fully depreciated.']]);
            $transaction = AssetTransaction::create(['tenant_id' => $tenant, 'asset_id' => $locked->id, 'type' => 'depreciation', 'transaction_date' => $data['transaction_date'], 'amount' => $monthly, 'created_by' => $request->user()->id]); $locked->update(['accumulated_depreciation' => Decimal::add((string) $locked->accumulated_depreciation, $monthly)]); return $transaction;
        });
        return response()->json(['data' => $transaction], 201);
    }

    private function scope(Request $request, AuthorizationService $authorization): array
    {
        $tenant = (string) app(TenantContext::class)->id(); $company = $request->string('company_id')->toString() ?: null; if ($company) { abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $company), 403); return [$tenant, [$company]]; } $companies = $authorization->accessibleCompanyIds($request->user(), $tenant, 'assets.manage'); abort_unless($companies === null || $companies !== [], 403); return [$tenant, $companies];
    }

    private function authorizeAsset(Request $request, FixedAsset $asset, AuthorizationService $authorization): void
    {
        $tenant = (string) app(TenantContext::class)->id(); abort_unless($asset->tenant_id === $tenant, 404); abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $asset->company_id), 403);
    }
}
