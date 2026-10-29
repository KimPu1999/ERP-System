<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void { if (DB::getDriverName() !== 'pgsql') return; foreach (['suppliers', 'purchase_requests', 'purchase_request_items', 'rfqs', 'supplier_quotations', 'supplier_quotation_items', 'purchase_orders', 'purchase_order_items', 'procurement_approvals', 'goods_receipts', 'goods_receipt_items', 'purchase_returns', 'purchase_return_items', 'supplier_bills', 'procurement_document_history'] as $table) { DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY"); DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))"); } }
    public function down(): void { if (DB::getDriverName() !== 'pgsql') return; foreach (['suppliers', 'purchase_requests', 'purchase_request_items', 'rfqs', 'supplier_quotations', 'supplier_quotation_items', 'purchase_orders', 'purchase_order_items', 'procurement_approvals', 'goods_receipts', 'goods_receipt_items', 'purchase_returns', 'purchase_return_items', 'supplier_bills', 'procurement_document_history'] as $table) { DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}"); DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY"); } }
};
