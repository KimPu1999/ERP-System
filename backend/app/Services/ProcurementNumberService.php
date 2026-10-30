<?php

namespace App\Services;

use App\Models\PurchasingNumberingPolicy;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcurementNumberService
{
    public function make(string $prefix): string { return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)); }

    public function makeFor(string $prefix, string $tenantId, ?string $companyId, ?string $branchId = null): string
    {
        if (! $companyId) return $this->make($prefix);
        return DB::transaction(function () use ($prefix, $tenantId, $companyId, $branchId): string {
            $policy = PurchasingNumberingPolicy::where('tenant_id', $tenantId)->where('company_id', $companyId)->where('document_type', strtoupper($prefix))->lockForUpdate()->first();
            if (! $policy) return $this->make($prefix);
            $sequence = (int) $policy->next_sequence; $policy->update(['next_sequence' => $sequence + 1]);
            $parts = [$policy->prefix]; if ($policy->include_year) $parts[] = now()->format('Y');
            if ($policy->include_branch_code && $branchId) { $code = DB::table('branches')->where('id', $branchId)->value('code'); if ($code) $parts[] = strtoupper((string) $code); }
            $parts[] = str_pad((string) $sequence, (int) $policy->sequence_length, '0', STR_PAD_LEFT);
            return implode('-', $parts);
        });
    }
}
