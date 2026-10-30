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

    public function assets(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); [$tenant, $companies] = $this->scope($request, $authorization); $query = FixedAsset::where('tenant_id', $tenant)->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')))->with('transactions')->orderBy('asset_number'); return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function acquire(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $tenant = app(TenantContext::class)->id(); $data = $request->validate(['company_id' => 'required|uuid', 'category_id' => 'required|uuid', 'asset_number' => 'required|alpha_dash|max:50', 'name' => 'required|string|max:200', 'acquired_on' => 'required|date', 'capitalized_on' => 'nullable|date', 'acquisition_cost' => 'required|numeric|gt:0', 'residual_value' => 'nullable|numeric|min:0', 'useful_life_months' => 'required|integer|min:1', 'depreciation_method' => 'required|in:straight_line']);
        abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $data['company_id']), 403); abort_unless(Company::where('tenant_id', $tenant)->whereKey($data['company_id'])->exists(), 404); abort_unless(AssetCategory::where('tenant_id', $tenant)->where('company_id', $data['company_id'])->whereKey($data['category_id'])->exists(), 404);
        $asset = DB::transaction(function () use ($data, $tenant, $request) { $asset = FixedAsset::create([...$data, 'tenant_id' => $tenant, 'state' => 'capitalized', 'accumulated_depreciation' => '0']); AssetTransaction::create(['tenant_id' => $tenant, 'asset_id' => $asset->id, 'type' => 'acquisition', 'transaction_date' => $data['acquired_on'], 'amount' => $data['acquisition_cost'], 'created_by' => $request->user()->id, 'description' => 'Asset acquisition']); return $asset; });
        event(new \App\Events\FixedAssetCapitalized($tenant, $asset->id, $asset->company_id, (string) $asset->acquisition_cost)); return response()->json(['data' => $asset->load('transactions')], 201);
    }

    public function depreciate(Request $request, FixedAsset $asset, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('fixed_assets'); $tenant = app(TenantContext::class)->id(); abort_unless($asset->tenant_id === $tenant, 404); abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $asset->company_id), 403); $data = $request->validate(['transaction_date' => 'required|date']);
        $transaction = DB::transaction(function () use ($asset, $data, $request, $tenant) {
            $locked = FixedAsset::whereKey($asset->id)->where('tenant_id', $tenant)->lockForUpdate()->firstOrFail(); if (AssetTransaction::where('asset_id', $locked->id)->where('type', 'depreciation')->whereDate('transaction_date', $data['transaction_date'])->exists()) throw ValidationException::withMessages(['transaction_date' => ['Depreciation has already been recorded for this asset and period.']]);
            $remaining = Decimal::sub((string) $locked->acquisition_cost, (string) $locked->residual_value); $monthly = Decimal::div($remaining, (string) $locked->useful_life_months); $next = Decimal::add((string) $locked->accumulated_depreciation, $monthly); if (Decimal::compare($next, $remaining) > 0) $monthly = Decimal::sub($remaining, (string) $locked->accumulated_depreciation); if (Decimal::compare($monthly, '0') <= 0) throw ValidationException::withMessages(['asset' => ['The asset is fully depreciated.']]);
            $transaction = AssetTransaction::create(['tenant_id' => $tenant, 'asset_id' => $locked->id, 'type' => 'depreciation', 'transaction_date' => $data['transaction_date'], 'amount' => $monthly, 'created_by' => $request->user()->id]); $locked->update(['accumulated_depreciation' => Decimal::add((string) $locked->accumulated_depreciation, $monthly)]); return $transaction;
        });
        return response()->json(['data' => $transaction], 201);
    }

    private function scope(Request $request, AuthorizationService $authorization): array
    {
        $tenant = (string) app(TenantContext::class)->id(); $company = $request->string('company_id')->toString() ?: null; if ($company) { abort_unless($authorization->allows($request->user(), $tenant, 'assets.manage', $company), 403); return [$tenant, [$company]]; } $companies = $authorization->accessibleCompanyIds($request->user(), $tenant, 'assets.manage'); abort_unless($companies === null || $companies !== [], 403); return [$tenant, $companies];
    }
}
