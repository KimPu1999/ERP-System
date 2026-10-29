<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantOnboardingController
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'slug' => ['required', 'alpha_dash', 'max:80', 'unique:tenants,slug']]);
        [$tenant, $membership] = DB::transaction(function () use ($data, $request): array { $tenant = Tenant::create($data); $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id, 'status' => 'active']); $permissions = collect(['roles.manage', 'members.invite', 'company.manage', 'organization.manage', 'products.manage', 'inventory.manage', 'procurement.manage', 'sales.manage', 'sales.approve', 'accounting.manage', 'accounting.post', 'accounting.close', 'hr.manage', 'hr.approve', 'payroll.manage', 'payroll.approve', 'payroll.view', 'pos.manage', 'pos.sell', 'projects.manage', 'projects.time', 'assets.manage', 'approvals.manage', 'approvals.request', 'reports.view', 'reports.export', 'reports.schedule', 'automation.manage'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name])) ; $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Tenant Owner', 'slug' => 'tenant-owner']); $role->permissions()->sync($permissions->pluck('id')); RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $request->user()->id]); return [$tenant, $membership]; });
        app(SubscriptionService::class)->provisionTrial($tenant);
        $audit->record($request, 'tenant.created', $tenant->id, $tenant);
        return response()->json(['data' => $tenant, 'membership' => $membership], 201);
    }
}
