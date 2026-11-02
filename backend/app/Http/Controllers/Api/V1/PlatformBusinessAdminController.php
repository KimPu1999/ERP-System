<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\CompanyInvitationMail;
use App\Models\{Company, Invitation, Role, RoleAssignment, Tenant, TenantMembership, User};
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Platform-only controls for business administrators.
 *
 * Invitation and membership changes deliberately reuse the existing tenant,
 * company, role and invitation records. No platform endpoint accepts a role
 * or tenant scope without resolving it against the target tenant.
 */
final class PlatformBusinessAdminController
{
    public function index(Tenant $tenant): JsonResponse
    {
        $memberships = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('user.roleAssignments', fn ($query) => $query->where('tenant_id', $tenant->id)->whereHas('role.permissions', fn ($permissions) => $permissions->whereIn('name', ['organization.manage', 'members.invite'])))
            ->with(['user:id,name,email,status,email_verified_at,last_login_at', 'user.roleAssignments' => fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('company_id')->with('role:id,name,slug')])
            ->orderByDesc('created_at')
            ->paginate(min(100, max(10, (int) request()->query('per_page', 25))));

        $rows = $memberships->through(fn (TenantMembership $membership) => $this->membershipPayload($membership));
        return response()->json(['data' => $rows]);
    }

    public function invite(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        abort_if($tenant->status === 'suspended', 403, 'Suspended businesses cannot receive administrator invitations.');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'company_id' => ['required', 'uuid'],
            'role_id' => ['required', 'uuid'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'personal_message' => ['nullable', 'string', 'max:2000'],
        ]);
        $company = Company::whereKey($data['company_id'])->where('tenant_id', $tenant->id)->where('is_active', true)->firstOrFail();
        $role = Role::whereKey($data['role_id'])->where('tenant_id', $tenant->id)->with('permissions')->firstOrFail();
        abort_if($role->permissions->pluck('name')->intersect(['organization.manage', 'members.invite'])->isEmpty(), 422, 'The selected role is not an administrator role.');

        $email = strtolower(trim($data['email']));
        abort_if(Invitation::where('tenant_id', $tenant->id)->where('company_id', $company->id)->where('email', $email)->where('status', 'pending')->where('expires_at', '>', now())->exists(), 422, 'A pending invitation already exists for this administrator.');
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        abort_if($existing && TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $existing->id)->whereIn('status', ['active', 'suspended'])->exists(), 422, 'This user already has a membership in the business.');

        $token = Str::random(64);
        $invitation = Invitation::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'inviter_user_id' => $request->user()->id,
            'role_id' => $role->id,
            'email' => $email,
            'personal_message' => $data['personal_message'] ?? null,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
            'status' => 'pending',
        ]);

        try {
            Mail::to($email)->send(new CompanyInvitationMail($invitation, $token));
        } catch (\Throwable) {
            $audit->record($request, 'platform.business_admin.invitation_delivery_failed', $tenant->id, $invitation, ['company_id' => $company->id]);
            return response()->json(['message' => 'The invitation was created, but the configured mail provider could not accept it.', 'invitation_id' => $invitation->id], 503);
        }

        $audit->record($request, 'platform.business_admin.invited', $tenant->id, $invitation, ['company_id' => $company->id, 'role_id' => $role->id]);
        return response()->json(['data' => $this->invitationPayload($invitation->load(['company:id,name,code', 'role:id,name,slug']))], 201);
    }

    public function invitations(Request $request, Tenant $tenant): JsonResponse
    {
        Invitation::where('tenant_id', $tenant->id)->where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        $query = Invitation::where('tenant_id', $tenant->id)->with(['company:id,name,code', 'role:id,name,slug'])->latest();
        if ($status = $request->query('status')) $query->where('status', $status);
        $invitations = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))))->through(fn (Invitation $invitation) => $this->invitationPayload($invitation));
        return response()->json(['data' => $invitations]);
    }

    public function updateMembership(Request $request, Tenant $tenant, TenantMembership $membership, AuditLogger $audit): JsonResponse
    {
        abort_unless($membership->tenant_id === $tenant->id, 404);
        $data = $request->validate(['status' => ['sometimes', 'in:active,suspended,revoked'], 'role_id' => ['sometimes', 'uuid']]);
        $role = null;
        if (isset($data['role_id'])) $role = Role::whereKey($data['role_id'])->where('tenant_id', $tenant->id)->with('permissions')->firstOrFail();

        DB::transaction(function () use ($request, $tenant, $membership, $data, $role, $audit): void {
            $locked = TenantMembership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $isOwner = $this->isAdministrator($locked);
            if ($isOwner && isset($data['status']) && $data['status'] !== 'active') $this->protectLastAdministrator($tenant, $locked);
            if ($isOwner && $role && $role->permissions->pluck('name')->intersect(['organization.manage', 'members.invite'])->isEmpty()) $this->protectLastAdministrator($tenant, $locked);

            $before = ['status' => $locked->status, 'role_id' => $locked->user?->roleAssignments()->where('tenant_id', $tenant->id)->whereNull('company_id')->value('role_id')];
            if (isset($data['status'])) $locked->update(['status' => $data['status'], 'suspended_at' => $data['status'] === 'suspended' ? now() : null, 'revoked_at' => $data['status'] === 'revoked' ? now() : null]);
            if ($role) {
                $assignment = RoleAssignment::where('tenant_id', $tenant->id)->where('user_id', $locked->user_id)->whereNull('company_id')->whereNull('branch_id')->whereNull('department_id')->first();
                if ($assignment) $assignment->update(['role_id' => $role->id]);
                else RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $locked->user_id]);
            }
            if (isset($data['status']) && $data['status'] !== 'active') $locked->user?->tokens()->delete();
            $audit->record($request, 'platform.business_admin.membership_updated', $tenant->id, $locked, ['before' => $before, 'after' => ['status' => $locked->status, 'role_id' => $role?->id ?? $before['role_id']]]);
        }, 3);

        return response()->json(['data' => $this->membershipPayload($membership->fresh(['user:id,name,email,status,email_verified_at,last_login_at']))]);
    }

    public function transferOwnership(Request $request, Tenant $tenant, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'from_membership_id' => ['required', 'uuid'],
            'to_membership_id' => ['required', 'uuid', 'different:from_membership_id'],
            'replacement_role_id' => ['required', 'uuid'],
        ]);
        $ownerRole = Role::where('tenant_id', $tenant->id)->whereIn('slug', ['business-owner', 'tenant-owner'])->first();
        abort_unless($ownerRole, 422, 'This business does not have an ownership role configured.');
        $replacementRole = Role::whereKey($data['replacement_role_id'])->where('tenant_id', $tenant->id)->with('permissions')->firstOrFail();
        abort_if($replacementRole->id === $ownerRole->id, 422, 'The outgoing owner must be assigned a non-owner role.');
        abort_if($replacementRole->permissions->pluck('name')->intersect(['organization.manage', 'members.invite'])->isEmpty(), 422, 'The replacement role must retain administrator access.');

        $result = DB::transaction(function () use ($request, $tenant, $data, $ownerRole, $replacementRole, $audit): TenantMembership {
            $source = TenantMembership::whereKey($data['from_membership_id'])->where('tenant_id', $tenant->id)->with('user')->lockForUpdate()->firstOrFail();
            $target = TenantMembership::whereKey($data['to_membership_id'])->where('tenant_id', $tenant->id)->with('user')->lockForUpdate()->firstOrFail();
            abort_if($source->status !== 'active' || $target->status !== 'active', 422, 'Both memberships must be active before ownership can be transferred.');
            abort_if(!$target->user?->email_verified_at, 422, 'The incoming owner must have a verified email address.');
            abort_unless($this->isAdministrator($source), 422, 'The outgoing membership is not an active administrator.');
            $sourceAssignment = RoleAssignment::where('tenant_id', $tenant->id)->where('user_id', $source->user_id)->whereNull('company_id')->whereNull('branch_id')->whereNull('department_id')->lockForUpdate()->first();
            $targetAssignment = RoleAssignment::where('tenant_id', $tenant->id)->where('user_id', $target->user_id)->whereNull('company_id')->whereNull('branch_id')->whereNull('department_id')->lockForUpdate()->first();
            abort_unless($sourceAssignment && $sourceAssignment->role_id === $ownerRole->id, 422, 'The outgoing membership is not the current owner.');
            $sourceAssignment->update(['role_id' => $replacementRole->id]);
            if ($targetAssignment) $targetAssignment->update(['role_id' => $ownerRole->id]);
            else RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $ownerRole->id, 'user_id' => $target->user_id]);
            $audit->record($request, 'platform.business_owner.transferred', $tenant->id, $target, ['from_membership_id' => $source->id, 'to_membership_id' => $target->id, 'replacement_role_id' => $replacementRole->id]);
            return $target;
        }, 3);

        return response()->json(['data' => $this->membershipPayload($result->fresh(['user:id,name,email,status,email_verified_at,last_login_at']))]);
    }

    public function resendInvitation(Request $request, Tenant $tenant, Invitation $invitation, AuditLogger $audit): JsonResponse
    {
        abort_unless($invitation->tenant_id === $tenant->id, 404);
        abort_if(in_array($invitation->status, ['accepted', 'revoked'], true), 422, 'This invitation cannot be resent.');
        $token = Str::random(64);
        $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'status' => 'pending', 'revoked_at' => null]);
        try {
            Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $token));
        } catch (\Throwable) {
            $audit->record($request, 'platform.business_admin.invitation_delivery_failed', $tenant->id, $invitation);
            return response()->json(['message' => 'The mail provider could not accept the resent invitation.'], 503);
        }
        $audit->record($request, 'platform.business_admin.invitation_resent', $tenant->id, $invitation);
        return response()->json(['data' => $this->invitationPayload($invitation->fresh(['company:id,name,code', 'role:id,name,slug']))]);
    }

    private function isAdministrator(TenantMembership $membership): bool
    {
        return RoleAssignment::where('tenant_id', $membership->tenant_id)->where('user_id', $membership->user_id)->whereHas('role.permissions', fn ($query) => $query->whereIn('name', ['organization.manage', 'members.invite']))->exists();
    }

    private function protectLastAdministrator(Tenant $tenant, TenantMembership $target): void
    {
        $count = TenantMembership::where('tenant_id', $tenant->id)->where('status', 'active')->whereHas('user.roleAssignments', fn ($query) => $query->where('tenant_id', $tenant->id)->whereHas('role.permissions', fn ($permissions) => $permissions->whereIn('name', ['organization.manage', 'members.invite'])))->count();
        abort_if($count <= 1, 422, 'The last active business administrator must be retained or transferred first.');
    }

    private function membershipPayload(TenantMembership $membership): array
    {
        $roles = $membership->user?->roleAssignments?->map(fn (RoleAssignment $assignment) => $assignment->role?->only(['id', 'name', 'slug']))->filter()->unique('id')->values() ?? collect();
        return ['id' => $membership->id, 'tenant_id' => $membership->tenant_id, 'status' => $membership->status, 'created_at' => $membership->created_at?->toISOString(), 'user' => $membership->user?->only(['id', 'name', 'email', 'status', 'email_verified_at', 'last_login_at']), 'roles' => $roles];
    }

    private function invitationPayload(Invitation $invitation): array
    {
        return ['id' => $invitation->id, 'email' => $invitation->email, 'company' => $invitation->company?->only(['id', 'name', 'code']), 'role' => $invitation->role?->only(['id', 'name', 'slug']), 'status' => $invitation->status, 'expires_at' => $invitation->expires_at?->toISOString(), 'created_at' => $invitation->created_at?->toISOString()];
    }
}
