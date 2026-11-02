<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Company, Invitation, Permission, Role, RoleAssignment, Tenant, TenantMembership, TenantModule, TenantSubscription, User};
use App\Services\{AuditLogger, SubscriptionService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PlatformTenantController
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()->withCount(['users', 'companies', 'memberships'])->with(['modules', 'subscription.plan'])->orderBy('name');
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder->where('name', 'ilike', "%{$search}%")->orWhere('slug', 'ilike', "%{$search}%"));
        }
        if ($status = $request->query('status')) $query->where('status', $status);
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $tenants = $query->paginate($perPage);

        return response()->json([
            'data' => $tenants,
            'summary' => $this->summaryData(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit, SubscriptionService $subscriptions): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:80', 'unique:tenants,slug'],
            'status' => ['sometimes', 'in:pending,active'],
        ]);

        $tenant = DB::transaction(function () use ($data, $subscriptions): Tenant {
            $tenant = Tenant::create([...$data, 'status' => $data['status'] ?? 'pending']);
            $companyCode = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $tenant->slug), 0, 12)) ?: 'BUSINESS';
            Company::create(['tenant_id' => $tenant->id, 'name' => $tenant->name, 'code' => $companyCode]);
            $permissions = collect(['roles.manage', 'members.invite', 'company.manage', 'organization.manage', 'products.manage', 'inventory.manage', 'procurement.manage', 'sales.manage', 'sales.approve', 'accounting.manage', 'accounting.post', 'hr.manage', 'pos.manage', 'pos.sell', 'projects.manage', 'assets.manage', 'approvals.manage', 'approvals.request', 'reports.view', 'reports.export'])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]));
            $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Business Owner', 'slug' => 'business-owner']);
            $role->permissions()->sync($permissions->pluck('id')->all());
            $subscriptions->provisionTrial($tenant);
            return $tenant;
        }, 3);

        $audit->record($request, 'platform.tenant.created', $tenant->id, $tenant);
        return response()->json(['data' => $this->tenantPayload($tenant->fresh())], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->tenantPayload($tenant->load(['modules', 'companies']))]);
    }

    public function update(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'required', 'alpha_dash', 'max:80', 'unique:tenants,slug,'.$tenant->id],
        ]);
        $before = $tenant->only(['name', 'slug']);
        $tenant->update($data);
        $audit->record($request, 'platform.tenant.updated', $tenant->id, $tenant, ['before' => $before, 'after' => $tenant->only(['name', 'slug'])]);
        return response()->json(['data' => $this->tenantPayload($tenant->fresh())]);
    }

    public function changeStatus(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:pending,active,suspended']]);
        abort_if($tenant->status === $data['status'], 422, 'The tenant already has this status.');
        $previous = $tenant->status;
        $tenant->update(['status' => $data['status']]);
        $audit->record($request, 'platform.tenant.status_changed', $tenant->id, $tenant, ['from' => $previous, 'to' => $tenant->status]);
        return response()->json(['data' => $this->tenantPayload($tenant->fresh()), 'status' => $tenant->status]);
    }

    public function summary(): JsonResponse
    {
        return response()->json(['summary' => $this->summaryData()]);
    }

    private function summaryData(): array
    {
        return [
            'total_businesses' => Tenant::count(),
            'active_businesses' => Tenant::where('status', 'active')->count(),
            'pending_approvals' => Tenant::where('status', 'pending')->count(),
            'suspended_businesses' => Tenant::where('status', 'suspended')->count(),
            'business_administrators' => $this->administratorQuery()->distinct('role_assignments.user_id')->count('role_assignments.user_id'),
            'pending_invitations' => Invitation::where('status', 'pending')->where('expires_at', '>', now())->count(),
            'enabled_modules' => TenantModule::where('enabled', true)->count(),
        ];
    }

    private function tenantPayload(Tenant $tenant): array
    {
        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->with('plan')->first();
        $roles = Role::where('tenant_id', $tenant->id)->with('permissions:id,name')->orderBy('name')->get(['id', 'tenant_id', 'name', 'slug']);
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'created_at' => $tenant->created_at?->toISOString(),
            'users_count' => $tenant->users_count ?? $tenant->memberships()->count(),
            'companies_count' => $tenant->companies_count ?? $tenant->companies()->count(),
            'companies' => $tenant->relationLoaded('companies') ? $tenant->companies->map->only(['id', 'name', 'code', 'is_active'])->values() : [],
            'modules' => $tenant->relationLoaded('modules') ? $tenant->modules->map->only(['id', 'module_key', 'enabled', 'settings'])->values() : [],
            'subscription' => $subscription?->only(['id', 'state', 'billing_cycle', 'trial_ends_at', 'current_period_ends_at', 'seat_count']) + ['plan' => $subscription?->plan?->only(['id', 'code', 'name'])],
            'roles' => $roles->map(fn (Role $role) => $role->only(['id', 'name', 'slug']) + ['permissions' => $role->permissions->pluck('name')->values()])->values(),
        ];
    }

    private function administratorQuery()
    {
        return RoleAssignment::query()
            ->join('tenant_memberships', function ($join): void { $join->on('tenant_memberships.user_id', '=', 'role_assignments.user_id')->on('tenant_memberships.tenant_id', '=', 'role_assignments.tenant_id')->where('tenant_memberships.status', 'active'); })
            ->whereHas('role.permissions', fn ($query) => $query->whereIn('name', ['organization.manage', 'members.invite']));
    }
}
