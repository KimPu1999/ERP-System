<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            ['tenants', 'erp_analytics_tenants_created_status_idx', '(created_at, status)'],
            ['users', 'erp_analytics_users_created_login_idx', '(created_at, last_login_at, status)'],
            ['tenant_memberships', 'erp_analytics_memberships_tenant_status_idx', '(tenant_id, status, user_id)'],
            ['tenant_modules', 'erp_analytics_modules_enabled_key_idx', '(enabled, module_key, tenant_id)'],
            ['platform_audit_logs', 'erp_analytics_audit_action_created_idx', '(action, created_at)'],
            ['platform_invoices', 'erp_analytics_invoices_issued_status_currency_idx', '(issued_on, status, currency_code)'],
            ['platform_payments', 'erp_analytics_payments_verified_status_currency_idx', '(verified_at, status, currency_code)'],
        ];
        foreach ($indexes as [$table, $name, $columns]) DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} {$columns}");
    }

    public function down(): void
    {
        foreach (['erp_analytics_tenants_created_status_idx', 'erp_analytics_users_created_login_idx', 'erp_analytics_memberships_tenant_status_idx', 'erp_analytics_modules_enabled_key_idx', 'erp_analytics_audit_action_created_idx', 'erp_analytics_invoices_issued_status_currency_idx', 'erp_analytics_payments_verified_status_currency_idx'] as $index) DB::statement("DROP INDEX IF EXISTS {$index}");
    }
};
