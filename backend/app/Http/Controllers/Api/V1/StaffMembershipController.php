<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\PlatformAuditLog;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\StaffAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SecurityEventLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Mail\CompanyInvitationMail;

final class StaffMembershipController
{
    private const READ_PERMISSIONS = ['members.invite', 'organization.manage', 'company.manage', 'reports.view'];
    private const MANAGE_PERMISSIONS = ['members.invite', 'organization.manage', 'company.manage'];

    public function index(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($request, $company, $authorization, self::READ_PERMISSIONS);
        $query = CompanyMembership::query()->where('company_id', $company->id)->with([
            'user:id,name,email,status,last_login_at,created_at',
            'user.roleAssignments' => fn ($q) => $q->where('tenant_id', $company->tenant_id)->with('role:id,name,slug'),
        ]);
        if ($search = trim((string) $request->query('search'))) $query->whereHas('user', fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
        if ($status = $request->query('status')) $query->where('status', $status);
        if ($request->filled('role_id')) $query->whereHas('user.roleAssignments', fn ($q) => $q->where('role_id', $request->query('role_id'))->where('company_id', $company->id));
        if ($request->filled('branch_id')) $query->whereHas('user.staffAssignments', fn ($q) => $q->where('company_id', $company->id)->where('branch_id', $request->query('branch_id'))->where('is_primary', true));
        if ($request->filled('department_id')) $query->whereHas('user.staffAssignments', fn ($q) => $q->where('company_id', $company->id)->where('department_id', $request->query('department_id'))->where('is_primary', true));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'name') $query->join('users', 'users.id', '=', 'company_memberships.user_id')->orderBy('users.name', $direction)->select('company_memberships.*');
        else $query->orderBy(in_array($sort, ['status', 'created_at'], true) ? "company_memberships.{$sort}" : 'company_memberships.created_at', $direction);
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $members = $query->paginate($perPage)->through(fn (CompanyMembership $membership) => $this->memberPayload($membership, $company));
        $pending = Invitation::query()->where('company_id', $company->id)->where('status', 'pending')->where('expires_at', '>', now())->count();
        return response()->json(['data' => $members, 'summary' => [
            'total_members' => CompanyMembership::where('company_id', $company->id)->count(),
            'active_members' => CompanyMembership::where('company_id', $company->id)->where('status', 'active')->count(),
            'pending_invitations' => $pending,
            'suspended_members' => CompanyMembership::where('company_id', $company->id)->where('status', 'suspended')->count(),
        ]]);
    }

    public function show(Request $request, Company $company, User $member, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($request, $company, $authorization, self::READ_PERMISSIONS);
        $membership = CompanyMembership::where('company_id', $company->id)->where('user_id', $member->id)->with(['user', 'company'])->firstOrFail();
        $assignments = StaffAssignment::where('company_id', $company->id)->where('user_id', $member->id)->with(['branch:id,name,code', 'department:id,name,code', 'team:id,name,code', 'reportingManager:id,name,email'])->orderByDesc('is_primary')->latest()->get();
        $roles = RoleAssignment::where('tenant_id', $company->tenant_id)->where('user_id', $member->id)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))->with('role.permissions')->get()->map(fn (RoleAssignment $assignment) => ['id' => $assignment->role?->id, 'name' => $assignment->role?->name, 'slug' => $assignment->role?->slug, 'company_id' => $assignment->company_id]);
        $activity = PlatformAuditLog::where('tenant_id', $company->tenant_id)->where(function ($q) use ($membership, $member): void { $q->where('auditable_id', $membership->id)->orWhere('auditable_id', $member->id); })->latest()->limit(50)->get(['id', 'action', 'actor_user_id', 'metadata', 'created_at']);
        return response()->json(['data' => ['member' => $this->memberPayload($membership, $company), 'roles' => $roles, 'assignments' => $assignments, 'activity' => $activity]]);
    }

    public function update(Request $request, Company $company, User $member, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompany($request, $company, $authorization, self::MANAGE_PERMISSIONS);
        $membership = CompanyMembership::where('company_id', $company->id)->where('user_id', $member->id)->firstOrFail();
        abort_unless($membership->status === 'active', 422, 'Only active memberships can be assigned.');
        $data = $request->validate([
            'role_ids' => ['sometimes', 'array'], 'role_ids.*' => ['uuid'], 'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid'], 'team_id' => ['nullable', 'uuid'], 'reporting_manager_id' => ['nullable', 'uuid'], 'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $this->validateAssignment($company, $member, $data);
        DB::transaction(function () use ($data, $company, $member, $request, $audit, $membership): void {
            if (array_key_exists('role_ids', $data)) {
                $roles = Role::where('tenant_id', $company->tenant_id)->whereIn('id', $data['role_ids'])->get();
                abort_if($roles->count() !== count(array_unique($data['role_ids'])), 422, 'One or more roles are not valid for this workspace.');
                foreach ($roles as $role) $this->assertRoleAssignable($request->user(), $company, $role);
                RoleAssignment::where('tenant_id', $company->tenant_id)->where('user_id', $member->id)->where('company_id', $company->id)->delete();
                foreach ($roles as $role) RoleAssignment::create(['tenant_id' => $company->tenant_id, 'role_id' => $role->id, 'user_id' => $member->id, 'company_id' => $company->id]);
            }
            if (collect($data)->hasAny(['branch_id', 'department_id', 'team_id', 'reporting_manager_id', 'effective_from', 'effective_to'])) {
                StaffAssignment::where('company_id', $company->id)->where('user_id', $member->id)->where('is_primary', true)->update(['is_primary' => false, 'effective_to' => now()->toDateString()]);
                StaffAssignment::create(['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'user_id' => $member->id, 'branch_id' => $data['branch_id'] ?? null, 'department_id' => $data['department_id'] ?? null, 'team_id' => $data['team_id'] ?? null, 'reporting_manager_id' => $data['reporting_manager_id'] ?? null, 'effective_from' => $data['effective_from'] ?? now()->toDateString(), 'effective_to' => $data['effective_to'] ?? null, 'is_primary' => true]);
            }
            $audit->record($request, 'company.membership.updated', $company->tenant_id, $membership, ['company_id' => $company->id, 'member_id' => $member->id]);
        });
        return response()->json(['data' => $this->memberPayload($membership->fresh('user'), $company)]);
    }

    public function status(Request $request, Company $company, User $member, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompany($request, $company, $authorization, self::MANAGE_PERMISSIONS);
        $membership = CompanyMembership::where('company_id', $company->id)->where('user_id', $member->id)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'revoked'])]]);
        if ($membership->status === 'revoked' && $data['status'] !== 'revoked') abort(422, 'A revoked membership must be re-invited.');
        if (in_array($data['status'], ['suspended', 'revoked'], true)) $this->protectLastOwner($company, $membership);
        $membership->update(['status' => $data['status'], 'suspended_at' => $data['status'] === 'suspended' ? now() : null, 'revoked_at' => $data['status'] === 'revoked' ? now() : null]);
        $audit->record($request, "company.membership.{$data['status']}", $company->tenant_id, $membership, ['company_id' => $company->id, 'member_id' => $member->id]);
        return response()->json(['data' => $this->memberPayload($membership->fresh('user'), $company)]);
    }

    public function activity(Request $request, Company $company, User $member, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($request, $company, $authorization, self::READ_PERMISSIONS);
        $membership = CompanyMembership::where('company_id', $company->id)->where('user_id', $member->id)->firstOrFail();
        return response()->json(['data' => PlatformAuditLog::where('tenant_id', $company->tenant_id)->where(function ($q) use ($membership, $member): void { $q->where('auditable_id', $membership->id)->orWhere('auditable_id', $member->id); })->latest()->paginate(50)]);
    }

    public function teams(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($request, $company, $authorization, self::READ_PERMISSIONS);
        return response()->json(['data' => Team::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    private function memberPayload(CompanyMembership $membership, Company $company): array
    {
        $user = $membership->user;
        $assignment = StaffAssignment::where('company_id', $company->id)->where('user_id', $user->id)->where('is_primary', true)->with(['branch:id,name,code', 'department:id,name,code', 'team:id,name,code'])->first();
        $roles = RoleAssignment::where('tenant_id', $company->tenant_id)->where('user_id', $user->id)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))->with('role:id,name,slug')->get()->pluck('role.name')->filter()->unique()->values()->all();
        return ['id' => $user->id, 'membership_id' => $membership->id, 'name' => $user->name, 'email' => $user->email, 'status' => $membership->status, 'user_status' => $user->status, 'company' => ['id' => $company->id, 'name' => $company->name], 'roles' => $roles, 'branch' => $assignment?->branch, 'department' => $assignment?->department, 'team' => $assignment?->team, 'last_login_at' => $user->last_login_at ?? null, 'joined_at' => $membership->joined_at?->toISOString(), 'created_at' => $membership->created_at?->toISOString()];
    }

    private function assertCompany(Request $request, Company $company, AuthorizationService $authorization, array $permissions): void
    {
        abort_unless($company->tenant_id === app(TenantContext::class)->id(), 404);
        abort_unless($company->is_active, 403, 'Company is inactive.');
        foreach ($permissions as $permission) if ($authorization->allows($request->user(), $company->tenant_id, $permission, $company->id)) return;
        abort(403);
    }

    private function validateAssignment(Company $company, User $member, array $data): void
    {
        abort_unless($member->tenantMemberships()->where('tenant_id', $company->tenant_id)->where('status', 'active')->exists(), 422, 'Member does not belong to this workspace.');
        if (! empty($data['branch_id'])) abort_unless(Branch::whereKey($data['branch_id'])->where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->where('status', 'active')->exists(), 422, 'Branch is not active or does not belong to this company.');
        if (! empty($data['department_id'])) abort_unless(Department::whereKey($data['department_id'])->where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->exists(), 422, 'Department does not belong to this company.');
        if (! empty($data['team_id'])) abort_unless(Team::whereKey($data['team_id'])->where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->where('is_active', true)->exists(), 422, 'Team does not belong to this company.');
        if (! empty($data['reporting_manager_id'])) abort_unless(CompanyMembership::where('company_id', $company->id)->where('user_id', $data['reporting_manager_id'])->where('status', 'active')->exists(), 422, 'Reporting manager is not an active company member.');
    }

    private function assertRoleAssignable(User $actor, Company $company, Role $role): void
    {
        if (app(AuthorizationService::class)->allows($actor, $company->tenant_id, 'roles.manage', $company->id)) return;
        $actorPermissions = RoleAssignment::where('tenant_id', $company->tenant_id)->where('user_id', $actor->id)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))->with('role.permissions')->get()->flatMap(fn ($a) => $a->role?->permissions?->pluck('name') ?? [])->unique();
        $targetPermissions = $role->load('permissions')->permissions->pluck('name');
        abort_if($targetPermissions->diff($actorPermissions)->isNotEmpty(), 403, 'You cannot assign a role with permissions you do not hold.');
    }

    private function protectLastOwner(Company $company, CompanyMembership $target): void
    {
        $ownerIds = CompanyMembership::where('company_id', $company->id)->where('status', 'active')->whereHas('user.roleAssignments', fn ($q) => $q->where('tenant_id', $company->tenant_id)->where(fn ($scope) => $scope->whereNull('company_id')->orWhere('company_id', $company->id))->whereHas('role.permissions', fn ($p) => $p->whereIn('name', ['company.manage', 'organization.manage'])))->pluck('id');
        abort_if($ownerIds->count() <= 1 && $ownerIds->contains($target->id), 422, 'The last authorized company administrator cannot be removed.');
    }
}
