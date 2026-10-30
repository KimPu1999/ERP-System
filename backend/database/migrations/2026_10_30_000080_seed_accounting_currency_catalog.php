<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $currencies = [
            ['code' => 'MMK', 'name' => 'Myanmar Kyat', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'United States Dollar', 'decimal_places' => 2],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'decimal_places' => 2],
            ['code' => 'THB', 'name' => 'Thai Baht', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'decimal_places' => 2],
        ];

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($currencies as $currency) {
                DB::table('accounting_currencies')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    ...$currency,
                    'is_base' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Currency rows may already be referenced by companies or documents;
        // preserve them rather than deleting historical accounting references.
    }
};
