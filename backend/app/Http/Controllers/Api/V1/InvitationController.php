<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\CompanyInvitationMail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\StaffAssignment;
use App\Models\Team;
use App\Models\TenantMembership;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SecurityEventLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class InvitationController
{
    public function store(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompanyManage($request, $company, $authorization);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'], 'role_id' => ['required', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid'], 'team_id' => ['nullable', 'uuid'], 'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'], 'personal_message' => ['nullable', 'string', 'max:2000'],
        ]);
        $email = strtolower(trim($data['email']));
        $this->validateInvitationScope($request, $company, $data, $authorization);
        abort_if(Invitation::where('company_id', $company->id)->where('email', $email)->where('status', 'pending')->where('expires_at', '>', now())->exists(), 422, 'A pending invitation already exists for this email.');
        $existing = \App\Models\User::whereRaw('LOWER(email) = ?', [$email])->first();
        abort_if($existing && CompanyMembership::where('company_id', $company->id)->where('user_id', $existing->id)->whereIn('status', ['active', 'suspended'])->exists(), 422, 'This user already has a company membership.');
        $token = Str::random(64);
        $invitation = Invitation::create(['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'inviter_user_id' => $request->user()->id, 'role_id' => $data['role_id'], 'branch_id' => $data['branch_id'] ?? null, 'department_id' => $data['department_id'] ?? null, 'team_id' => $data['team_id'] ?? null, 'email' => $email, 'personal_message' => $data['personal_message'] ?? null, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays($data['expires_in_days'] ?? 7), 'status' => 'pending']);
        try { Mail::to($email)->send(new CompanyInvitationMail($invitation, $token)); } catch (\Throwable $exception) { $audit->record($request, 'company.invitation.delivery_failed', $company->tenant_id, $invitation, ['company_id' => $company->id]); return response()->json(['message' => 'Invitation was created, but the configured mail provider could not accept it. You can retry from Pending Invitations.', 'invitation_id' => $invitation->id], 503); }
        $audit->record($request, 'company.invitation.created', $company->tenant_id, $invitation, ['company_id' => $company->id]);
        return response()->json(['data' => $this->invitationPayload($invitation), 'delivery' => 'accepted_by_mailer'], 201);
    }

    public function index(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompanyManage($request, $company, $authorization);
        Invitation::where('company_id', $company->id)->where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        $query = Invitation::where('company_id', $company->id)->with(['role:id,name,slug', 'inviter:id,name,email'])->latest();
        if ($search = trim((string) $request->query('search'))) $query->where('email', 'ilike', "%{$search}%");
        if ($status = $request->query('status')) $query->where('status', $status);
        return response()->json(['data' => $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))))->through(fn (Invitation $invitation) => $this->invitationPayload($invitation))]);
    }

    public function resend(Request $request, Company $company, Invitation $invitation, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompanyManage($request, $company, $authorization);
        abort_unless($invitation->company_id === $company->id, 404);
        abort_if(in_array($invitation->status, ['accepted', 'revoked'], true), 422, 'This invitation cannot be resent.');
        $token = Str::random(64);
        $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'status' => 'pending', 'revoked_at' => null]);
        try { Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $token)); } catch (\Throwable $exception) { $audit->record($request, 'company.invitation.delivery_failed', $company->tenant_id, $invitation); return response()->json(['message' => 'The mail provider could not accept the resent invitation.'], 503); }
        $audit->record($request, 'company.invitation.resent', $company->tenant_id, $invitation);
        return response()->json(['data' => $this->invitationPayload($invitation), 'delivery' => 'accepted_by_mailer']);
    }

    public function revoke(Request $request, Company $company, Invitation $invitation, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompanyManage($request, $company, $authorization);
        abort_unless($invitation->company_id === $company->id, 404);
        abort_if($invitation->status !== 'pending', 422, 'Only pending invitations can be revoked.');
        $invitation->update(['status' => 'revoked', 'revoked_at' => now(), 'token_hash' => hash('sha256', Str::random(64))]);
        $audit->record($request, 'company.invitation.revoked', $company->tenant_id, $invitation);
        return response()->json(['data' => $this->invitationPayload($invitation)]);
    }

    public function preview(string $token): JsonResponse
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->with(['company:id,name,code,is_active', 'role:id,name,slug'])->first();
        if (! $invitation) return response()->json(['status' => 'invalid'], 404);
        return response()->json(['data' => ['status' => $this->currentStatus($invitation), 'company' => $invitation->company?->only(['id', 'name', 'code']), 'role' => $invitation->role?->only(['id', 'name', 'slug']), 'expires_at' => $invitation->expires_at?->toISOString()]]);
    }

    public function accept(Request $request, SecurityEventLogger $events, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['invitation_id' => ['required', 'uuid'], 'token' => ['required', 'string']]);
        return $this->acceptInvitation($request, Invitation::whereKey($data['invitation_id'])->firstOrFail(), $data['token'], $events, $audit);
    }

    public function acceptToken(Request $request, string $token, SecurityEventLogger $events, AuditLogger $audit): JsonResponse
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        return $this->acceptInvitation($request, $invitation, $token, $events, $audit);
    }

    private function acceptInvitation(Request $request, Invitation $invitation, string $token, SecurityEventLogger $events, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        abort_if($this->currentStatus($invitation) !== 'pending', 410, 'Invitation is no longer valid.');
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)), 403, 'Invitation token is invalid.');
        abort_unless(strtolower($user->email) === strtolower($invitation->email), 403, 'Invitation email does not match the authenticated account.');
        abort_unless(\App\Models\Tenant::whereKey($invitation->tenant_id)->where('status', 'active')->exists(), 403, 'The invited workspace is inactive.');
        abort_unless(Company::whereKey($invitation->company_id)->where('tenant_id', $invitation->tenant_id)->where('is_active', true)->exists(), 403, 'The invited company is inactive.');
        DB::transaction(function () use ($request, $invitation, $audit): void {
            $locked = Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending' || $locked->accepted_at || $locked->revoked_at || $locked->expires_at->isPast(), 410, 'Invitation is no longer valid.');
            TenantMembership::firstOrCreate(['tenant_id' => $locked->tenant_id, 'user_id' => $request->user()->id], ['status' => 'active']);
            $membership = CompanyMembership::updateOrCreate(['company_id' => $locked->company_id, 'user_id' => $request->user()->id], ['tenant_id' => $locked->tenant_id, 'status' => 'active', 'joined_at' => now(), 'suspended_at' => null, 'revoked_at' => null]);
            if ($locked->role_id) RoleAssignment::firstOrCreate(['tenant_id' => $locked->tenant_id, 'role_id' => $locked->role_id, 'user_id' => $request->user()->id, 'company_id' => $locked->company_id]);
            if ($locked->branch_id || $locked->department_id || $locked->team_id) StaffAssignment::updateOrCreate(['company_id' => $locked->company_id, 'user_id' => $request->user()->id, 'is_primary' => true], ['tenant_id' => $locked->tenant_id, 'branch_id' => $locked->branch_id, 'department_id' => $locked->department_id, 'team_id' => $locked->team_id, 'effective_from' => now()->toDateString()]);
            $locked->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by_user_id' => $request->user()->id, 'token_hash' => hash('sha256', Str::random(64))]);
            $audit->record($request, 'company.invitation.accepted', $locked->tenant_id, $membership, ['invitation_id' => $locked->id, 'company_id' => $locked->company_id]);
        });
        $events->record($request, 'company.invitation.accepted', $user->id, $invitation->tenant_id, ['invitation_id' => $invitation->id]);
        return response()->json(['status' => 'accepted', 'tenant_id' => $invitation->tenant_id, 'company_id' => $invitation->company_id]);
    }

    private function validateInvitationScope(Request $request, Company $company, array $data, AuthorizationService $authorization): void
    {
        $role = Role::where('tenant_id', $company->tenant_id)->whereKey($data['role_id'])->firstOrFail();
        if (! $authorization->allows($request->user(), $company->tenant_id, 'roles.manage', $company->id)) {
            $actorPermissions = RoleAssignment::where('tenant_id', $company->tenant_id)->where('user_id', $request->user()->id)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))->with('role.permissions')->get()->flatMap(fn ($a) => $a->role?->permissions?->pluck('name') ?? [])->unique();
            abort_if($role->load('permissions')->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty(), 403, 'You cannot assign this role.');
        }
        if (! empty($data['branch_id'])) abort_unless(Branch::whereKey($data['branch_id'])->where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->where('status', 'active')->exists(), 422, 'Branch is not valid for this company.');
        if (! empty($data['department_id'])) abort_unless(Department::whereKey($data['department_id'])->where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->exists(), 422, 'Department is not valid for this company.');
        if (! empty($data['team_id'])) abort_unless(Team::whereKey($data['team_id'])->where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->where('is_active', true)->exists(), 422, 'Team is not valid for this company.');
    }

    private function assertCompanyManage(Request $request, Company $company, AuthorizationService $authorization): void
    {
        abort_unless($company->tenant_id === app(TenantContext::class)->id(), 404);
        abort_unless($company->is_active, 403, 'Company is inactive.');
        abort_unless($authorization->allows($request->user(), $company->tenant_id, 'members.invite', $company->id) || $authorization->allows($request->user(), $company->tenant_id, 'organization.manage', $company->id), 403);
    }

    private function currentStatus(Invitation $invitation): string
    {
        if ($invitation->status === 'pending' && $invitation->expires_at?->isPast()) { $invitation->update(['status' => 'expired']); return 'expired'; }
        return $invitation->status ?: ($invitation->accepted_at ? 'accepted' : 'pending');
    }

    private function invitationPayload(Invitation $invitation): array
    {
        return ['id' => $invitation->id, 'email' => $invitation->email, 'company_id' => $invitation->company_id, 'company_name' => $invitation->company?->name, 'role' => $invitation->role?->only(['id', 'name', 'slug']), 'invited_by' => $invitation->inviter?->only(['id', 'name', 'email']), 'branch_id' => $invitation->branch_id, 'department_id' => $invitation->department_id, 'team_id' => $invitation->team_id, 'status' => $this->currentStatus($invitation), 'expires_at' => $invitation->expires_at?->toISOString(), 'created_at' => $invitation->created_at?->toISOString()];
    }
}
