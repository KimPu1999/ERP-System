<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $tables = ['invitations', 'security_events'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        foreach ($this->tables as $table) {
            $condition = "(current_setting('app.platform_admin', true) = 'true' OR tenant_id IS NULL OR tenant_id::text = current_setting('app.tenant_id', true))";
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING ({$condition}) WITH CHECK ({$condition})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
