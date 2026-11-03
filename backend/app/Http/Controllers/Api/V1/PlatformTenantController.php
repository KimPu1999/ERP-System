<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\CompanyInvitationMail;
use App\Models\{Company, Invitation, Permission, PlatformAuditLog, Role, RoleAssignment, SecurityEvent, Tenant, TenantMembership, TenantModule, TenantSubscription, User};
use App\Services\{AuditLogger, SubscriptionService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class PlatformTenantController
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()
            ->withCount(['users', 'companies', 'memberships'])
            ->with(['modules', 'subscription.plan', 'companies' => fn ($q) => $q->withCount('branches'), 'memberships.user.roleAssignments.role.permissions'])
            ->orderBy('name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder->where('name', 'ilike', "%{$search}%")->orWhere('slug', 'ilike', "%{$search}%"));
        }
        if ($ownerEmail = trim((string) $request->query('owner_email'))) {
            $query->whereHas('memberships.user', fn ($builder) => $builder->whereRaw('LOWER(email) LIKE ?', ['%'.strtolower($ownerEmail).'%']));
        }
        if ($status = $request->query('status')) $query->where('status', $status);
        if ($planId = $request->query('plan_id')) $query->whereHas('subscription', fn ($builder) => $builder->where('plan_id', $planId));
        if ($from = $request->query('created_from')) $query->whereDate('created_at', '>=', $from);
        if ($to = $request->query('created_to')) $query->whereDate('created_at', '<=', $to);

        $tenants = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $tenants->through(fn (Tenant $tenant) => $this->tenantListPayload($tenant));
        return response()->json(['data' => $tenants, 'summary' => $this->summaryData()]);
    }

    public function store(Request $request, AuditLogger $audit, SubscriptionService $subscriptions): JsonResponse
    {
        $data = $request->validate($this->tenantRules());
        $ownerEmail = isset($data['owner_email']) ? strtolower(trim($data['owner_email'])) : null;
        $token = $ownerEmail ? Str::random(64) : null;
        [$tenant, $invitation] = DB::transaction(function () use ($data, $ownerEmail, $token, $subscriptions, $request): array {
            $tenant = Tenant::create(['name' => $data['name'], 'slug' => $data['slug'], 'status' => $data['status'] ?? 'pending']);
            $companyCode = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $tenant->slug), 0, 12)) ?: 'BUSINESS';
            $company = Company::create($this->companyAttributes($tenant, $data, $companyCode));
            $permissions = collect(['roles.manage', 'members.invite', 'company.manage', 'organization.manage', 'products.manage', 'inventory.manage', 'procurement.manage', 'sales.manage', 'sales.approve', 'accounting.manage', 'accounting.post', 'hr.manage', 'pos.manage', 'pos.sell', 'projects.manage', 'assets.manage', 'approvals.manage', 'approvals.request', 'reports.view', 'reports.export'])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]));
            $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Business Owner', 'slug' => 'business-owner']);
            $role->permissions()->sync($permissions->pluck('id')->all());
            $subscriptions->provisionTrial($tenant);
            $invitation = null;
            if ($ownerEmail && $token) {
                $invitation = Invitation::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'inviter_user_id' => $request->user()->id, 'role_id' => $role->id, 'email' => $ownerEmail, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'status' => 'pending']);
            }
            return [$tenant, $invitation];
        }, 3);
        $audit->record($request, 'platform.tenant.created', $tenant->id, $tenant, ['owner_email' => $ownerEmail]);
        if ($invitation && $token) {
            try {
                Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $token));
                $audit->record($request, 'platform.business_admin.invited', $tenant->id, $invitation, ['source' => 'tenant_creation']);
            } catch (\Throwable) {
                $audit->record($request, 'platform.business_admin.invitation_delivery_failed', $tenant->id, $invitation, ['source' => 'tenant_creation']);
                return response()->json(['message' => 'The business was created, but the owner invitation could not be delivered.', 'tenant_id' => $tenant->id], 503);
            }
        }
        return response()->json(['data' => $this->tenantPayload($tenant->fresh(['modules', 'companies']))], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => $this->tenantPayload($tenant->load(['modules', 'companies' => fn ($q) => $q->withCount('branches'), 'memberships.user.roleAssignments.role.permissions']))]);
    }

    public function update(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate($this->tenantRules(true));
        $before = $this->profileSnapshot($tenant);
        DB::transaction(function () use ($tenant, $data): void {
            $tenantData = array_intersect_key($data, array_flip(['name', 'slug']));
            if ($tenantData) $tenant->update($tenantData);
            $company = $tenant->companies()->oldest('created_at')->first();
            if ($company) {
                $company->update(array_filter(['name' => $data['name'] ?? null, 'email' => $data['business_email'] ?? null, 'phone' => $data['contact_phone'] ?? null, 'country_code' => $data['country_code'] ?? null, 'timezone' => $data['timezone'] ?? null, 'base_currency' => $data['base_currency'] ?? null, 'business_type' => $data['business_type'] ?? null, 'registration_number' => $data['registration_number'] ?? null, 'tax_identifier' => $data['tax_identifier'] ?? null, 'tax_registration_details' => $data['registration_details'] ?? null], fn ($value) => $value !== null));
            }
        });
        $tenant->refresh();
        $audit->record($request, 'platform.tenant.updated', $tenant->id, $tenant, ['before' => $before, 'after' => $this->profileSnapshot($tenant)]);
        return response()->json(['data' => $this->tenantPayload($tenant->fresh(['modules', 'companies']))]);
    }

    public function changeStatus(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:pending,active,suspended'], 'reason' => ['required_if:status,suspended', 'nullable', 'string', 'max:1000']]);
        $previous = $tenant->status;
        abort_if($previous === $data['status'], 422, 'The tenant already has this status.');
        abort_unless($this->allowedTransition($previous, $data['status']), 422, 'This business status transition is not allowed.');
        if ($data['status'] === 'active') abort_if(!$tenant->companies()->exists(), 422, 'The business must have a company before it can be activated.');
        DB::transaction(function () use ($tenant, $data): void {
            $tenant->update(['status' => $data['status']]);
            if ($data['status'] === 'suspended') TenantMembership::where('tenant_id', $tenant->id)->whereIn('status', ['active', 'suspended'])->with('user')->get()->each(fn (TenantMembership $membership) => $membership->user?->tokens()->delete());
        });
        $audit->record($request, 'platform.tenant.status_changed', $tenant->id, $tenant, ['from' => $previous, 'to' => $tenant->status, 'reason' => $data['reason'] ?? null]);
        return response()->json(['data' => $this->tenantPayload($tenant->fresh(['modules', 'companies'])), 'status' => $tenant->status]);
    }

    public function summary(): JsonResponse
    {
        return response()->json(['summary' => $this->summaryData()]);
    }

    public function security(Tenant $tenant): JsonResponse
    {
        $memberships = TenantMembership::query()->where('tenant_id', $tenant->id)->with('user:id,name,email,status,last_login_at,mfa_enabled_at')->latest()->get();
        return response()->json(['data' => $memberships->map(fn (TenantMembership $membership) => ['id' => $membership->id, 'status' => $membership->status, 'user' => ($membership->user?->only(['id', 'name', 'email', 'status', 'last_login_at']) ?? []) + ['mfa_enabled' => (bool) $membership->user?->mfa_enabled_at]])->values()]);
    }

    public function activity(Request $request, Tenant $tenant): JsonResponse
    {
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $logs = PlatformAuditLog::query()->where('tenant_id', $tenant->id)->with('actor:id,name,email')->latest()->paginate($perPage)->through(fn (PlatformAuditLog $log) => ['id' => $log->id, 'action' => $log->action, 'actor' => $log->actor?->only(['id', 'name', 'email']), 'auditable_type' => $log->auditable_type, 'auditable_id' => $log->auditable_id, 'created_at' => $log->created_at?->toISOString()]);
        return response()->json(['data' => $logs]);
    }

    private function tenantRules(bool $update = false): array
    {
        return ['name' => [$update ? 'sometimes' : 'required', 'string', 'max:150'], 'slug' => [$update ? 'sometimes' : 'required', 'alpha_dash', 'max:80', $update ? 'unique:tenants,slug,'.$this->routeTenantId() : 'unique:tenants,slug'], 'status' => ['sometimes', 'in:pending,active'], 'owner_email' => ['nullable', 'email', 'max:255'], 'business_email' => ['nullable', 'email', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:50'], 'country_code' => ['nullable', 'string', 'size:2'], 'timezone' => ['nullable', 'timezone'], 'base_currency' => ['nullable', 'string', 'size:3'], 'business_type' => ['nullable', 'string', 'max:120'], 'registration_number' => ['nullable', 'string', 'max:120'], 'tax_identifier' => ['nullable', 'string', 'max:120'], 'registration_details' => ['nullable', 'string', 'max:2000']];
    }

    private function routeTenantId(): string { $tenant = request()->route('tenant'); return $tenant instanceof Tenant ? $tenant->id : ''; }

    private function companyAttributes(Tenant $tenant, array $data, string $code): array
    {
        return array_filter(['tenant_id' => $tenant->id, 'name' => $data['name'], 'code' => $code, 'email' => $data['business_email'] ?? null, 'phone' => $data['contact_phone'] ?? null, 'country_code' => $data['country_code'] ?? null, 'timezone' => $data['timezone'] ?? null, 'base_currency' => $data['base_currency'] ?? null, 'business_type' => $data['business_type'] ?? null, 'registration_number' => $data['registration_number'] ?? null, 'tax_identifier' => $data['tax_identifier'] ?? null, 'tax_registration_details' => $data['registration_details'] ?? null], fn ($value) => $value !== null && $value !== '');
    }

    private function allowedTransition(string $from, string $to): bool { return match ($from) { 'pending' => $to === 'active', 'active' => $to === 'suspended', 'suspended' => $to === 'active', default => false }; }

    private function summaryData(): array
    {
        $monthStart = now()->startOfMonth()->subMonths(5);
        $registrations = Tenant::query()->latest()->limit(6)->get(['id', 'name', 'slug', 'status', 'created_at']);
        $activities = PlatformAuditLog::query()->with('actor:id,name,email')->latest()->limit(8)->get();
        $growth = Tenant::query()
            ->where('created_at', '>=', $monthStart)
            ->get(['created_at'])
            ->groupBy(fn (Tenant $tenant): string => $tenant->created_at->format('Y-m'))
            ->map(fn ($items, string $month): array => ['month' => $month, 'count' => $items->count()])
            ->values();

        $pastDueSubscriptions = TenantSubscription::whereIn('state', ['past_due', 'unpaid'])->count();
        $failedSecurityEvents = SecurityEvent::whereIn('event', ['auth.login_failed', 'auth.login_blocked'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'total_businesses' => Tenant::count(),
            'active_businesses' => Tenant::where('status', 'active')->count(),
            'pending_approvals' => Tenant::where('status', 'pending')->count(),
            'suspended_businesses' => Tenant::where('status', 'suspended')->count(),
            'business_administrators' => $this->administratorQuery()->distinct('role_assignments.user_id')->count('role_assignments.user_id'),
            'pending_invitations' => Invitation::where('status', 'pending')->where('expires_at', '>', now())->count(),
            'enabled_modules' => TenantModule::where('enabled', true)->count(),
            'total_platform_users' => User::where('is_platform_super_admin', true)->count(),
            'active_subscriptions' => TenantSubscription::whereIn('state', ['active', 'trialing'])->count(),
            'system_alerts' => [
                'total' => Tenant::where('status', 'pending')->count() + $pastDueSubscriptions + $failedSecurityEvents,
                'pending_businesses' => Tenant::where('status', 'pending')->count(),
                'past_due_subscriptions' => $pastDueSubscriptions,
                'failed_logins_last_24h' => $failedSecurityEvents,
            ],
            'business_growth' => $growth,
            'recent_registrations' => $registrations->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at?->toISOString(),
            ])->values(),
            'recent_activities' => $activities->map(fn (PlatformAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor?->only(['id', 'name', 'email']),
                'tenant_id' => $log->tenant_id,
                'created_at' => $log->created_at?->toISOString(),
            ])->values(),
        ];
    }

    private function tenantListPayload(Tenant $tenant): array
    {
        $payload = $this->tenantPayload($tenant);
        $owner = $tenant->memberships->first(fn (TenantMembership $membership) => $membership->status === 'active' && $membership->user && $membership->user->roleAssignments->contains(fn (RoleAssignment $assignment) => $assignment->tenant_id === $tenant->id && $assignment->role?->permissions?->pluck('name')->intersect(['organization.manage', 'members.invite'])->isNotEmpty()));
        $payload['owner_admin'] = $owner?->user?->only(['id', 'name', 'email']);
        $payload['branches_count'] = $tenant->companies->sum('branches_count');
        return $payload;
    }

    private function tenantPayload(Tenant $tenant): array
    {
        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->with('plan')->first();
        $roles = Role::where('tenant_id', $tenant->id)->with('permissions:id,name')->orderBy('name')->get(['id', 'tenant_id', 'name', 'slug']);
        $payload = ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'status' => $tenant->status, 'created_at' => $tenant->created_at?->toISOString(), 'users_count' => $tenant->users_count ?? $tenant->memberships()->count(), 'companies_count' => $tenant->companies_count ?? $tenant->companies()->count(), 'companies' => $tenant->relationLoaded('companies') ? $tenant->companies->map(fn (Company $company) => $company->only(['id', 'name', 'code', 'is_active', 'email', 'phone', 'country_code', 'timezone', 'base_currency', 'business_type', 'registration_number', 'tax_identifier', 'tax_registration_details']) + ['branches_count' => $company->branches_count ?? $company->branches()->count()])->values() : [], 'modules' => $tenant->relationLoaded('modules') ? $tenant->modules->map->only(['id', 'module_key', 'enabled', 'settings'])->values() : [], 'subscription' => $subscription ? $subscription->only(['id', 'state', 'billing_cycle', 'trial_ends_at', 'current_period_ends_at', 'seat_count']) + ['plan' => $subscription->plan?->only(['id', 'code', 'name'])] : null, 'roles' => $roles->map(fn (Role $role) => $role->only(['id', 'name', 'slug']) + ['permissions' => $role->permissions->pluck('name')->values()])->values()];
        if ($tenant->relationLoaded('memberships')) {
            $owner = $tenant->memberships->first(fn (TenantMembership $membership) => $membership->status === 'active' && $membership->user && $membership->user->roleAssignments->contains(fn (RoleAssignment $assignment) => $assignment->tenant_id === $tenant->id && $assignment->role?->permissions?->pluck('name')->intersect(['organization.manage', 'members.invite'])->isNotEmpty()));
            $payload['owner_admin'] = $owner?->user?->only(['id', 'name', 'email']);
        }
        return $payload;
    }

    private function profileSnapshot(Tenant $tenant): array
    {
        $company = $tenant->companies()->oldest('created_at')->first();
        return ['name' => $tenant->name, 'slug' => $tenant->slug, 'company' => $company?->only(['name', 'email', 'phone', 'country_code', 'timezone', 'base_currency', 'business_type', 'registration_number', 'tax_identifier', 'tax_registration_details'])];
    }

    private function administratorQuery()
    {
        return RoleAssignment::query()->join('tenant_memberships', function ($join): void { $join->on('tenant_memberships.user_id', '=', 'role_assignments.user_id')->on('tenant_memberships.tenant_id', '=', 'role_assignments.tenant_id')->where('tenant_memberships.status', 'active'); })->whereHas('role.permissions', fn ($query) => $query->whereIn('name', ['organization.manage', 'members.invite']));
    }
}
