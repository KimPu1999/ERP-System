<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant;
use App\Services\{AuditLogger, AuthorizationService, OnboardingProgressService};
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BusinessOnboardingController
{
    public function show(Request $request, OnboardingProgressService $service): JsonResponse
    {
        $tenant = Tenant::findOrFail(app(TenantContext::class)->id());
        return response()->json(['data' => $service->overview($tenant, $request->user()->id)]);
    }

    public function complete(Request $request, string $step, OnboardingProgressService $service, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenant = Tenant::findOrFail(app(TenantContext::class)->id());
        abort_unless($authorization->allows($request->user(), $tenant->id, 'organization.manage'), 403, 'Only an authorized business administrator can complete onboarding steps.');
        $overview = $service->complete($tenant, $step, $request->user()->id);
        $audit->record($request, 'business.onboarding.step_completed', $tenant->id, null, ['step_key' => $step, 'schema_version' => $overview['schema_version']]);
        return response()->json(['data' => $overview]);
    }
}
