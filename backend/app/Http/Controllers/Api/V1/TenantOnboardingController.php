<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantOnboardingController
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'slug' => ['required', 'alpha_dash', 'max:80']]);
        [$tenant, $membership, $created] = DB::transaction(function () use ($data, $request): array {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = TenantMembership::query()->where('user_id', $user->id)->where('status', 'active')->with('tenant')->first();
            if ($existing?->tenant) return [$existing->tenant, $existing, false];

            $hasMembership = TenantMembership::query()->where('user_id', $user->id)->exists();
            $hasInvitation = Invitation::query()->whereRaw('LOWER(email) = LOWER(?)', [$user->email])->where('status', 'pending')->whereNull('accepted_at')->where('expires_at', '>', now())->exists();
            abort_unless(! $hasMembership && ! $hasInvitation, 403, 'This account must use its existing workspace access or invitation flow.');
            if (Tenant::query()->where('slug', $data['slug'])->exists()) {
                throw ValidationException::withMessages(['slug' => ['The workspace slug has already been taken.']]);
            }

            $tenant = Tenant::create($data);
            $membership = TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
            $permissions = collect(['roles.manage', 'members.invite', 'company.manage', 'organization.manage', 'products.manage', 'inventory.manage', 'procurement.manage', 'sales.manage', 'sales.approve', 'accounting.manage', 'accounting.post', 'accounting.close', 'hr.manage', 'hr.approve', 'payroll.manage', 'payroll.approve', 'payroll.view', 'pos.manage', 'pos.sell', 'projects.manage', 'projects.time', 'assets.manage', 'approvals.manage', 'approvals.request', 'reports.view', 'reports.export', 'reports.schedule', 'automation.manage'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name]));
            $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Tenant Owner', 'slug' => 'tenant-owner']);
            $role->permissions()->sync($permissions->pluck('id'));
            RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
            return [$tenant, $membership, true];
        }, 3);

        if ($created) {
            app(SubscriptionService::class)->provisionTrial($tenant);
            $audit->record($request, 'tenant.created', $tenant->id, $tenant);
        }

        return response()->json(['data' => $tenant, 'membership' => $membership, 'already_exists' => ! $created], $created ? 201 : 200);
    }
}
