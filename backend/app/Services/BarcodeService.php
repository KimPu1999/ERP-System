<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Support\Decimal;
use Illuminate\Support\Str;

final class BarcodeService
{
    public function internal(string $tenantId): string
    {
        do { $barcode = 'ERP'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10)); } while (ProductVariant::where('tenant_id', $tenantId)->where('internal_barcode', $barcode)->exists());
        return $barcode;
    }
}
