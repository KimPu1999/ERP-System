<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_integration_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('automatic_posting_enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->foreignUuid('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'company_id']);
        });
        DB::statement('ALTER TABLE accounting_integration_settings ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY accounting_integration_settings_tenant_isolation ON accounting_integration_settings USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS accounting_integration_settings_tenant_isolation ON accounting_integration_settings');
        DB::statement('ALTER TABLE accounting_integration_settings DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('accounting_integration_settings');
    }
};
