<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $indexes = [
        ['sales_orders', 'erp_sales_orders_tenant_state_created_idx', '(tenant_id, state, created_at)'],
        ['sales_invoices', 'erp_sales_invoices_tenant_state_created_idx', '(tenant_id, state, created_at)'],
        ['stock_movements', 'erp_stock_movements_tenant_variant_location_idx', '(tenant_id, variant_id, location_id, created_at)'],
        ['inventory_balances', 'erp_inventory_balances_tenant_variant_location_idx', '(tenant_id, variant_id, location_id)'],
        ['journal_entries', 'erp_journal_entries_tenant_company_date_idx', '(tenant_id, company_id, entry_date)'],
        ['journal_lines', 'erp_journal_lines_tenant_account_idx', '(tenant_id, account_id)'],
        ['approval_requests', 'erp_approval_requests_tenant_state_idx', '(tenant_id, state, created_at)'],
        ['platform_audit_logs', 'erp_audit_logs_tenant_created_idx', '(tenant_id, created_at)'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $name, $columns]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} {$columns}");
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [, $name]) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
