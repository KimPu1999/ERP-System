<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE sales_settings ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY sales_settings_tenant_isolation ON sales_settings USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
    }
    public function down(): void { DB::statement('DROP POLICY IF EXISTS sales_settings_tenant_isolation ON sales_settings'); DB::statement('ALTER TABLE sales_settings DISABLE ROW LEVEL SECURITY'); }
};
