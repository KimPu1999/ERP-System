<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS platform_settings_platform_only ON platform_settings');
        DB::statement("CREATE POLICY platform_settings_platform_only ON platform_settings USING (current_setting('app.platform_admin', true) = 'true' OR (current_setting('app.platform_settings_policy_read', true) = 'true' AND setting_key IN ('business', 'subscriptions'))) WITH CHECK (current_setting('app.platform_admin', true) = 'true')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS platform_settings_platform_only ON platform_settings');
        DB::statement("CREATE POLICY platform_settings_platform_only ON platform_settings USING (current_setting('app.platform_admin', true) = 'true') WITH CHECK (current_setting('app.platform_admin', true) = 'true')");
    }
};
