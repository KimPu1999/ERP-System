<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Support\TenantContext;

final class PurchaseOrderResolver
{
    public function resolve(string $id): PurchaseOrder
    {
        $tenantId = (string) app(TenantContext::class)->id();

        $purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        abort_unless($purchaseOrder, 404, 'Purchase order not found.');

        return $purchaseOrder;
    }
}
