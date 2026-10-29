<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\CompanyInvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\TenantMembership;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SecurityEventLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class InvitationController
{
    public function store(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        abort_unless($company->tenant_id === app(TenantContext::class)->id(), 404); abort_unless($authorization->allows($request->user(), $company->tenant_id, 'members.invite', $company->id), 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role_id' => ['nullable', 'uuid']]); if (! empty($data['role_id'])) abort_unless(Role::where('tenant_id', $company->tenant_id)->whereKey($data['role_id'])->exists(), 404); $token = Str::random(64); $invitation = Invitation::create(['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'inviter_user_id' => $request->user()->id, 'role_id' => $data['role_id'] ?? null, 'email' => strtolower($data['email']), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]); Mail::to($invitation->email)->queue(new CompanyInvitationMail($invitation, $token)); $audit->record($request, 'company.invitation.created', $company->tenant_id, $invitation, ['company_id' => $company->id]); return response()->json(['data' => ['id' => $invitation->id, 'email' => $invitation->email, 'expires_at' => $invitation->expires_at]], 201);
    }

    public function accept(Request $request, SecurityEventLogger $events): JsonResponse
    {
        $data = $request->validate(['invitation_id' => ['required', 'uuid'], 'token' => ['required', 'string']]); $invitation = Invitation::whereKey($data['invitation_id'])->firstOrFail(); abort_if($invitation->accepted_at || $invitation->expires_at->isPast(), 410, 'Invitation is no longer valid.'); abort_unless(hash_equals($invitation->token_hash, hash('sha256', $data['token'])), 403, 'Invitation token is invalid.'); abort_unless(strtolower($request->user()->email) === strtolower($invitation->email), 403, 'Invitation email does not match the authenticated account.');
        $invitation->load('role'); \Illuminate\Support\Facades\DB::transaction(function () use ($invitation, $request): void { TenantMembership::firstOrCreate(['tenant_id' => $invitation->tenant_id, 'user_id' => $request->user()->id], ['status' => 'active']); if ($invitation->role_id) \App\Models\RoleAssignment::firstOrCreate(['tenant_id' => $invitation->tenant_id, 'role_id' => $invitation->role_id, 'user_id' => $request->user()->id, 'company_id' => $invitation->company_id]); $invitation->update(['accepted_at' => now()]); }); $events->record($request, 'company.invitation.accepted', $request->user()->id, $invitation->tenant_id, ['invitation_id' => $invitation->id]); return response()->json(['status' => 'accepted', 'tenant_id' => $invitation->tenant_id, 'company_id' => $invitation->company_id]);
    }
}
