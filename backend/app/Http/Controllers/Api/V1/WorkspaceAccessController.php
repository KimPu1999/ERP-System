<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Invitation;
use App\Models\TenantMembership;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceAccessController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = TenantMembership::query()
            ->where('user_id', $user->id)
            ->with(['tenant' => fn ($query) => $query->select(['id', 'name', 'slug', 'status'])->withCount([
                'companies as active_companies_count' => fn ($companyQuery) => $companyQuery->where('is_active', true),
            ])])
            ->get();

        $active = [];
        $restricted = [];
        foreach ($memberships as $membership) {
            $tenant = $membership->tenant;
            if (! $tenant) {
                continue;
            }
            $workspace = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'tenant_status' => $tenant->status,
                'membership_status' => $membership->status,
                'has_active_company' => (int) $tenant->active_companies_count > 0,
            ];
            if ($membership->status === 'active' && $tenant->status === 'active') {
                $active[] = [...$workspace, 'status' => 'active'];
            } else {
                $status = $tenant->status !== 'active' ? 'tenant_inactive' : 'membership_'.$membership->status;
                $restricted[] = [...$workspace, 'status' => $status];
            }
        }

        $context = app(TenantContext::class);
        $previous = $context->snapshot();
        $context->setUserEmail((string) $user->email);
        try {
            $invitations = Invitation::query()
                ->whereRaw('LOWER(email) = LOWER(?)', [$user->email])
                ->where('status', 'pending')
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->orderBy('expires_at')
                ->get(['id', 'tenant_id', 'company_id', 'email', 'expires_at']);
            // The pending invitee is entitled to the workspace summary shown
            // on the access screen. Load those relations under the bounded
            // tenant context granted by each invitation (same grant pattern
            // as the accept flow); without it, enforced row level security
            // would hide the tenant and company.
            foreach ($invitations->groupBy('tenant_id') as $tenantId => $tenantInvitations) {
                $context->set((string) $tenantId);
                $tenantInvitations->each(fn (Invitation $invitation): bool => (($invitation->setRelation('tenant', $invitation->tenant()->select(['id', 'name', 'slug', 'status'])->first())) && true));
                $tenantInvitations->each(fn (Invitation $invitation): bool => (($invitation->setRelation('company', $invitation->company()->select(['id', 'tenant_id', 'name', 'code', 'is_active'])->first())) && true));
            }
        } finally {
            $context->restoreSnapshot($previous);
        }

        return response()->json(['data' => [
            'platform_admin' => (bool) $user->is_platform_super_admin,
            'active_workspaces' => $active,
            'restricted_workspaces' => $restricted,
            'pending_invitations' => $invitations->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'tenant_id' => $invitation->tenant_id,
                'tenant_name' => $invitation->tenant?->name,
                'company_id' => $invitation->company_id,
                'company_name' => $invitation->company?->name,
                'expires_at' => $invitation->expires_at?->toISOString(),
            ])->values(),
            'can_create_workspace' => ! $user->is_platform_super_admin
                && $user->status === 'active'
                && $user->hasVerifiedEmail()
                && $memberships->isEmpty()
                && $invitations->isEmpty(),
        ]]);
    }
}
