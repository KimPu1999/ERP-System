<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $indexes = [
        ['tenants', 'erp_tenants_created_status_idx', '(created_at, status)'],
        ['users', 'erp_platform_users_flag_status_idx', '(is_platform_super_admin, status)'],
        ['tenant_subscriptions', 'erp_subscriptions_state_period_idx', '(state, current_period_ends_at, trial_ends_at)'],
        ['security_events', 'erp_security_events_event_created_idx', '(event, created_at)'],
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
