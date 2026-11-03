<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AssetCategory, Company, PosRegister, Tenant, TenantModule};
use App\Services\{AuthorizationService, OptionalModuleService, SubscriptionService};
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
            $status = 'disabled'; $readiness = 'disabled'; $reason = 'The module is disabled for this tenant.';
            if ($enabled && ! $hasCompanyAccess) { $status = 'enabled'; $readiness = 'permission_required'; $reason = 'Your account has no authorized company access for this module.'; }
            elseif ($enabled && ! $definition['setup']($companyIds)) { $status = 'enabled'; $readiness = 'setup_required'; $reason = $key === 'pos' ? 'Create an active POS register with a stock location before opening this module.' : ($key === 'fixed_assets' ? 'Create an asset category before opening this module.' : 'Complete the required module setup before opening this module.'); }
            elseif ($enabled) { $status = 'enabled'; $readiness = 'ready'; $reason = null; }
            return ['id' => $record?->id, 'module_key' => $key, 'enabled' => $enabled, 'status' => $status, 'readiness' => $readiness, 'reason' => $reason, 'can_open' => $enabled && $readiness === 'ready', 'can_configure' => false];
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

    public function platformUpdate(Request $request, Tenant $tenant, OptionalModuleService $modules, SubscriptionService $subscriptions)
    {
        $data = $request->validate(['module_key' => 'required|in:pos,projects,fixed_assets', 'enabled' => 'required|boolean']);
        return response()->json(['data' => $modules->set($tenant->id, $data['module_key'], $data['enabled'], $subscriptions)]);
    }

}
