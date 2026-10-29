<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS invitations_tenant_isolation ON invitations');
        $condition = "(current_setting('app.platform_admin', true) = 'true' OR tenant_id IS NULL OR tenant_id::text = current_setting('app.tenant_id', true) OR lower(email) = lower(current_setting('app.user_email', true)))";
        DB::statement("CREATE POLICY invitations_tenant_isolation ON invitations USING {$condition} WITH CHECK {$condition}");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS invitations_tenant_isolation ON invitations');
        $condition = "(current_setting('app.platform_admin', true) = 'true' OR tenant_id IS NULL OR tenant_id::text = current_setting('app.tenant_id', true))";
        DB::statement("CREATE POLICY invitations_tenant_isolation ON invitations USING {$condition} WITH CHECK {$condition}");
    }
};
