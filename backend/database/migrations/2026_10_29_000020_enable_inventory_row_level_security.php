<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void { if (DB::getDriverName() !== 'pgsql') return; foreach (['product_categories', 'brands', 'units_of_measure', 'products', 'product_variants', 'product_media', 'warehouses', 'warehouse_locations', 'inventory_settings', 'inventory_balances', 'stock_movements', 'stock_adjustments', 'stock_transfers', 'stock_transfer_lines', 'stock_counts', 'stock_count_lines', 'reorder_rules', 'inventory_alerts'] as $table) { DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY"); DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))"); } }
    public function down(): void { if (DB::getDriverName() !== 'pgsql') return; foreach (['product_categories', 'brands', 'units_of_measure', 'products', 'product_variants', 'product_media', 'warehouses', 'warehouse_locations', 'inventory_settings', 'inventory_balances', 'stock_movements', 'stock_adjustments', 'stock_transfers', 'stock_transfer_lines', 'stock_counts', 'stock_count_lines', 'reorder_rules', 'inventory_alerts'] as $table) { DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}"); DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY"); } }
};
