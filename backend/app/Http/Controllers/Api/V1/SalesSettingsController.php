<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SalesSetting;
use App\Services\{AuditLogger, AuthorizationService};
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

final class SalesSettingsController
{
    public function show(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->authorize($request, $authorization, $tenantId, 'sales.manage');
        $settings = SalesSetting::firstOrCreate(['tenant_id' => $tenantId]);

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $this->authorize($request, $authorization, $tenantId, 'sales.manage');
        $data = $request->validate([
            'approval_required' => ['required', 'boolean'],
            'invoice_on_order' => ['required', 'boolean'],
            'invoice_on_delivery' => ['required', 'boolean'],
        ]);

        $settings = DB::transaction(function () use ($tenantId, $data): SalesSetting {
            $settings = SalesSetting::where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (! $settings) {
                $settings = SalesSetting::create(['tenant_id' => $tenantId]);
            }
            $settings->update($data);
            return $settings->fresh();
        });
        $audit->record($request, 'sales.settings.changed', $tenantId, $settings, ['fields' => array_keys($data)]);

        return response()->json(['data' => $settings]);
    }

    private function authorize(Request $request, AuthorizationService $authorization, string $tenantId, string $permission): void
    {
        abort_unless($request->user() && $authorization->allows($request->user(), $tenantId, $permission), 403);
    }
}
