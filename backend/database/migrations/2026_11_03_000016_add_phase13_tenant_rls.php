<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TABLES = ['onboarding_progress', 'platform_invoice_items'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
