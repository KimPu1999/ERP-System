<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('erp_notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('erp_notifications', 'automation_run_id')) $table->foreignUuid('automation_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            if (! Schema::hasColumn('erp_notifications', 'rule_id')) $table->foreignUuid('rule_id')->nullable()->constrained('automation_rules')->nullOnDelete();
            if (! Schema::hasColumn('erp_notifications', 'supplier_id')) $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            if (! Schema::hasColumn('erp_notifications', 'company_id')) $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            if (! Schema::hasColumn('erp_notifications', 'source_key')) $table->string('source_key')->nullable();
            if (! Schema::hasColumn('erp_notifications', 'channel')) $table->string('channel', 30)->default('in_app');
            if (! Schema::hasColumn('erp_notifications', 'priority')) $table->string('priority', 20)->default('normal');
        });

        if (! Schema::hasTable('supplier_notification_deliveries')) {
            Schema::create('supplier_notification_deliveries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('notification_id')->constrained('erp_notifications')->cascadeOnDelete();
                $table->string('channel', 30);
                $table->string('state', 30)->default('queued');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('succeeded_at')->nullable();
                $table->timestamps();
                $table->unique(['notification_id', 'channel'], 'supplier_notif_delivery_unique');
                $table->index(['tenant_id', 'state'], 'supplier_notif_delivery_state_idx');
            });
        }

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS erp_notif_dedup_unique ON erp_notifications (tenant_id, user_id, type, source_key, channel) WHERE source_key IS NOT NULL");
        DB::statement("CREATE INDEX IF NOT EXISTS erp_notif_scope_idx ON erp_notifications (tenant_id, company_id, user_id, read_at)");

        foreach (['supplier_notification_deliveries'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS erp_notif_dedup_unique');
        DB::statement('DROP INDEX IF EXISTS erp_notif_scope_idx');
        if (Schema::hasTable('supplier_notification_deliveries')) {
            DB::statement('DROP POLICY IF EXISTS supplier_notification_deliveries_tenant_isolation ON supplier_notification_deliveries');
            DB::statement('ALTER TABLE supplier_notification_deliveries DISABLE ROW LEVEL SECURITY');
            Schema::drop('supplier_notification_deliveries');
        }
        Schema::table('erp_notifications', function (Blueprint $table): void {
            foreach (['automation_run_id', 'rule_id', 'supplier_id', 'company_id'] as $column) {
                if (Schema::hasColumn('erp_notifications', $column)) $table->dropForeign([$column]);
            }
            $columns = array_values(array_filter(['automation_run_id', 'rule_id', 'supplier_id', 'company_id', 'source_key', 'channel', 'priority'], fn (string $column): bool => Schema::hasColumn('erp_notifications', $column)));
            if ($columns !== []) $table->dropColumn($columns);
        });
    }
};
