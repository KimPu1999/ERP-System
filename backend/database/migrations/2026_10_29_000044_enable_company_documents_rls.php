<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        $condition = "(current_setting('app.platform_admin', true) = 'true' OR tenant_id IS NULL OR tenant_id::text = current_setting('app.tenant_id', true))";
        DB::statement('ALTER TABLE company_documents ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY company_documents_tenant_isolation ON company_documents USING {$condition} WITH CHECK {$condition}");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS company_documents_tenant_isolation ON company_documents');
        DB::statement('ALTER TABLE company_documents DISABLE ROW LEVEL SECURITY');
    }
};
