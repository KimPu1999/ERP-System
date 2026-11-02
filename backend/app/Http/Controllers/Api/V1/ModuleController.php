<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AssetCategory, Company, PosRegister, Tenant, TenantModule};
use App\Services\{AuthorizationService, OptionalModuleService};
use App\Support\TenantContext;
use Illuminate\Http\Request;

final class ModuleController
{
    public function index(Request $request, AuthorizationService $authorization)
    {
        $tenant = (string) app(TenantContext::class)->id();
        $stored = TenantModule::where('tenant_id', $tenant)->get()->keyBy('module_key');
        $definitions = [
            'pos' => ['permission' => 'pos.sell', 'setup' => fn (array $companies) => PosRegister::where('tenant_id', $tenant)->where('status', 'active')->whereNotNull('location_id')->when($companies !== [], fn ($q) => $q->whereIn('company_id', $companies))->exists()],
            'projects' => ['permission' => 'projects.manage', 'setup' => fn (array $companies) => true],
            'fixed_assets' => ['permission' => 'assets.manage', 'setup' => fn (array $companies) => AssetCategory::where('tenant_id', $tenant)->when($companies !== [], fn ($q) => $q->whereIn('company_id', $companies))->exists()],
        ];
        $rows = collect($definitions)->map(function (array $definition, string $key) use ($stored, $authorization, $request, $tenant): array {
            $record = $stored->get($key); $enabled = (bool) ($record?->enabled ?? false); $accessible = $authorization->accessibleCompanyIds($request->user(), $tenant, $definition['permission']);
            $companyIds = $accessible ?? Company::where('tenant_id', $tenant)->pluck('id')->all(); $hasCompanyAccess = $companyIds !== [];
            $status = 'disabled'; $reason = 'The module is disabled for this tenant.';
            if ($enabled && ! $hasCompanyAccess) { $status = 'unavailable'; $reason = 'No authorized company access is available for this module.'; }
            elseif ($enabled) { $status = 'enabled'; $reason = null; }
            return ['id' => $record?->id, 'module_key' => $key, 'enabled' => $enabled, 'status' => $status, 'reason' => $reason, 'can_open' => $status === 'enabled', 'can_configure' => false];
        })->values();
        return response()->json(['data' => $rows]);
    }

    public function platformIndex(Tenant $tenant)
    {
        $stored = TenantModule::where('tenant_id', $tenant->id)->get()->keyBy('module_key');
        $rows = collect(['pos', 'projects', 'fixed_assets'])->map(function (string $key) use ($stored): array {
            $record = $stored->get($key);
            $enabled = (bool) ($record?->enabled ?? false);
            return ['id' => $record?->id, 'module_key' => $key, 'enabled' => $enabled, 'status' => $enabled ? 'enabled' : 'disabled', 'reason' => $enabled ? null : 'The module is disabled for this tenant.', 'can_open' => $enabled, 'can_configure' => true];
        })->values();
        return response()->json(['data' => $rows]);
    }

    public function platformUpdate(Request $request, Tenant $tenant, OptionalModuleService $modules)
    {
        $data = $request->validate(['module_key' => 'required|in:pos,projects,fixed_assets', 'enabled' => 'required|boolean']);
        return response()->json(['data' => $modules->set($tenant->id, $data['module_key'], $data['enabled'])]);
    }

}
