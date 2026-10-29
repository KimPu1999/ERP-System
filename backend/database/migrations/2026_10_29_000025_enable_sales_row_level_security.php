<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void { if (DB::getDriverName() !== 'pgsql') return; foreach (['customers', 'customer_contacts', 'leads', 'opportunities', 'sales_quotations', 'sales_quotation_items', 'sales_orders', 'sales_order_items', 'sales_approvals', 'stock_reservations', 'delivery_orders', 'delivery_order_items', 'sales_invoices', 'sales_invoice_items', 'credit_notes', 'sales_returns', 'sales_return_items', 'payments_received', 'payment_allocations', 'sales_document_history'] as $table) { DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY"); DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))"); } }
    public function down(): void { if (DB::getDriverName() !== 'pgsql') return; foreach (['customers', 'customer_contacts', 'leads', 'opportunities', 'sales_quotations', 'sales_quotation_items', 'sales_orders', 'sales_order_items', 'sales_approvals', 'stock_reservations', 'delivery_orders', 'delivery_order_items', 'sales_invoices', 'sales_invoice_items', 'credit_notes', 'sales_returns', 'sales_return_items', 'payments_received', 'payment_allocations', 'sales_document_history'] as $table) { DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}"); DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY"); } }
};
