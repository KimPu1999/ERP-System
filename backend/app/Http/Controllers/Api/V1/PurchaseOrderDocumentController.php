<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseOrder;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PurchaseOrderDocumentController
{
    public function show(Request $request, PurchaseOrder $purchaseOrder, AuthorizationService $authorization): Response { $tenantId = (string) app(TenantContext::class)->id(); abort_unless((string) $purchaseOrder->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage', $purchaseOrder->company_id, $purchaseOrder->branch_id), 403); return response()->view('procurement.purchase-order', ['order' => $purchaseOrder->load(['supplier', 'company', 'branch', 'warehouse', 'currency', 'items.variant.product'])]); }
}
