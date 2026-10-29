<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Support\TenantContext;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompanyController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Company::query()->where('tenant_id', app(TenantContext::class)->id())->orderBy('name')->paginate(25)]);
    }

    public function show(Company $company): JsonResponse
    {
        abort_unless($company->tenant_id === app(TenantContext::class)->id(), 404);
        abort_unless(auth()->user()->can('view', $company), 403);
        return response()->json(['data' => $company]);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit, SubscriptionService $subscriptions): JsonResponse
    {
        abort_unless($authorization->allows($request->user(), app(TenantContext::class)->id(), 'company.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40'], 'tenant_id' => ['prohibited']]);
        $data['tenant_id'] = app(TenantContext::class)->id();
        $subscriptions->limit($data['tenant_id'], 'companies', Company::where('tenant_id', $data['tenant_id'])->count());
        $company = Company::create($data);
        $audit->record($request, 'company.created', $company->tenant_id, $company);
        return response()->json(['data' => $company], 201);
    }
}
