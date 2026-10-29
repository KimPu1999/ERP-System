<?php

use App\Mail\CompanyInvitationMail;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invitation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function staffFixture(array $permissions = ['members.invite', 'organization.manage', 'company.manage']): array
{
    $user = User::factory()->create(); $tenant = Tenant::factory()->create(); $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Staff Co', 'code' => 'STAFF']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']); CompanyMembership::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
    $permissionModels = collect($permissions)->map(fn ($name) => Permission::firstOrCreate(['name' => $name])); $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Staff administrator', 'slug' => 'staff-admin-'.Str::lower(Str::random(5))]); $role->permissions()->sync($permissionModels->pluck('id')); RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    $inviteRole = Role::create(['tenant_id' => $tenant->id, 'name' => 'Sales staff', 'slug' => 'sales-staff-'.Str::lower(Str::random(5))]);
    return [$user, $tenant, $company, $inviteRole];
}

it('invites and accepts a member with company-scoped assignments', function (): void {
    Mail::fake(); [$owner, $tenant, $company, $role] = staffFixture(); $invitee = User::factory()->create(['email' => 'new-member@example.test']);
    $this->actingAs($owner)->postJson('/api/v1/companies/'.$company->id.'/invitations', ['email' => $invitee->email, 'role_id' => $role->id], ['X-Tenant-Id' => $tenant->id])->assertCreated()->assertJsonPath('delivery', 'accepted_by_mailer');
    Mail::assertSent(CompanyInvitationMail::class); $mail = Mail::sent(CompanyInvitationMail::class)->first();
    $this->actingAs($invitee)->postJson('/api/v1/invitations/accept', ['invitation_id' => $mail->invitation->id, 'token' => $mail->token])->assertOk();
    expect(CompanyMembership::where('company_id', $company->id)->where('user_id', $invitee->id)->where('status', 'active')->exists())->toBeTrue()->and(RoleAssignment::where('company_id', $company->id)->where('user_id', $invitee->id)->where('role_id', $role->id)->exists())->toBeTrue();
});

it('rejects duplicate pending invitations and privilege escalation', function (): void {
    Mail::fake(); [$owner, $tenant, $company, $role] = staffFixture(['members.invite']); $privileged = Role::create(['tenant_id' => $tenant->id, 'name' => 'Finance owner', 'slug' => 'finance-owner']); $permission = Permission::firstOrCreate(['name' => 'accounting.manage']); $privileged->permissions()->sync([$permission->id]);
    $payload = ['email' => 'duplicate@example.test', 'role_id' => $role->id]; $this->actingAs($owner)->postJson('/api/v1/companies/'.$company->id.'/invitations', $payload, ['X-Tenant-Id' => $tenant->id])->assertCreated(); $this->actingAs($owner)->postJson('/api/v1/companies/'.$company->id.'/invitations', $payload, ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    $this->actingAs($owner)->postJson('/api/v1/companies/'.$company->id.'/invitations', ['email' => 'privileged@example.test', 'role_id' => $privileged->id], ['X-Tenant-Id' => $tenant->id])->assertForbidden();
});

it('rejects expired, revoked, and reused invitation tokens', function (): void {
    [$owner, $tenant, $company, $role] = staffFixture(); $invitee = User::factory()->create(['email' => 'expired@example.test']); $token = Str::random(64); $invitation = Invitation::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'inviter_user_id' => $owner->id, 'role_id' => $role->id, 'email' => $invitee->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute(), 'status' => 'pending']);
    $this->actingAs($invitee)->postJson('/api/v1/invitations/accept', ['invitation_id' => $invitation->id, 'token' => $token])->assertStatus(410);
    $token = Str::random(64); $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(), 'status' => 'revoked', 'revoked_at' => now()]); $this->actingAs($invitee)->postJson('/api/v1/invitations/accept', ['invitation_id' => $invitation->id, 'token' => $token])->assertStatus(410);
});

it('protects the last authorized company administrator and isolates member directories', function (): void {
    [$owner, $tenant, $company] = staffFixture(); $member = User::factory()->create(); TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $member->id, 'status' => 'active']); CompanyMembership::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'user_id' => $member->id, 'status' => 'active', 'joined_at' => now()]);
    $this->actingAs($owner)->patchJson('/api/v1/companies/'.$company->id.'/members/'.$owner->id.'/status', ['status' => 'suspended'], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    $otherTenant = Tenant::factory()->create(); $otherCompany = Company::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Staff', 'code' => 'OTHER-STAFF']);
    $this->actingAs($owner)->getJson('/api/v1/companies/'.$otherCompany->id.'/members', ['X-Tenant-Id' => $tenant->id])->assertNotFound();
    $this->actingAs($owner)->getJson('/api/v1/companies/'.$company->id.'/members?per_page=10', ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('summary.total_members', 2);
});
