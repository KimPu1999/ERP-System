<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseOrder;
use App\Support\TenantContext;
use Illuminate\Http\Response;

final class PurchaseOrderDocumentController
{
    public function show(PurchaseOrder $purchaseOrder): Response { abort_unless($purchaseOrder->tenant_id === app(TenantContext::class)->id(), 404); return response()->view('procurement.purchase-order', ['order' => $purchaseOrder->load(['supplier', 'items'])]); }
}
