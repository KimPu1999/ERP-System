<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AssetCategory, Company, PosRegister, TenantModule};
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
            elseif ($enabled && ! $definition['setup']($companyIds)) { $status = 'setup_required'; $reason = 'Required module configuration is incomplete.'; }
            elseif ($enabled) { $status = 'available'; $reason = null; }
            return ['id' => $record?->id, 'module_key' => $key, 'enabled' => $enabled, 'status' => $status, 'reason' => $reason, 'can_open' => $status === 'available', 'can_configure' => false];
        })->values();
        return response()->json(['data' => $rows]);
    }

    public function update(Request $request, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $tenant = app(TenantContext::class)->id(); abort_unless($auth->allows($request->user(), $tenant, 'organization.manage'), 403); $data = $request->validate(['module_key' => 'required|in:pos,projects,fixed_assets', 'enabled' => 'required|boolean']); return response()->json(['data' => $modules->set($tenant, $data['module_key'], $data['enabled'])]);
    }
}
