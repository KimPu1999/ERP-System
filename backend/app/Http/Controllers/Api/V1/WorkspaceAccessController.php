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
        $context->setUserEmail((string) $user->email);
        try {
            $invitations = Invitation::query()
                ->with([
                    'tenant:id,name,slug,status',
                    'company:id,tenant_id,name,code,is_active',
                ])
                ->whereRaw('LOWER(email) = LOWER(?)', [$user->email])
                ->where('status', 'pending')
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->orderBy('expires_at')
                ->get(['id', 'tenant_id', 'company_id', 'email', 'expires_at']);
        } finally {
            $context->clearUserEmail();
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
