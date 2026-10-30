<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_saved_report_views', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete(); $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 150); $t->string('report_key', 100); $t->unsignedSmallInteger('filter_schema_version')->default(1); $t->jsonb('filters')->nullable(); $t->boolean('is_default')->default(false); $t->boolean('is_shared')->default(false); $t->timestamps(); $t->index(['tenant_id','company_id','report_key']);
        });
        Schema::create('supplier_report_schedules', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $t->string('name', 150); $t->string('report_key', 100); $t->jsonb('filters')->nullable(); $t->string('frequency', 30); $t->string('timezone', 80); $t->string('format', 10)->default('csv'); $t->jsonb('recipients'); $t->boolean('enabled')->default(false); $t->timestamp('next_run_at')->nullable(); $t->timestamp('last_run_at')->nullable(); $t->foreignUuid('created_by')->constrained('users'); $t->timestamps(); $t->index(['tenant_id','company_id','enabled','next_run_at']);
        });
        Schema::create('supplier_automation_rule_versions', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('rule_id')->constrained('automation_rules')->cascadeOnDelete(); $t->unsignedInteger('version'); $t->jsonb('definition'); $t->foreignUuid('created_by')->constrained('users'); $t->timestamps(); $t->unique(['rule_id','version']);
        });
        Schema::create('supplier_access_reviews', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('membership_id')->nullable()->constrained('supplier_portal_memberships')->nullOnDelete(); $t->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete(); $t->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete(); $t->string('status', 30)->default('open'); $t->date('due_date')->nullable(); $t->text('decision_notes')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); $t->index(['tenant_id','company_id','status','due_date']);
        });
        foreach (['supplier_saved_report_views','supplier_report_schedules','supplier_automation_rule_versions','supplier_access_reviews'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
        }
    }
    public function down(): void
    {
        foreach (['supplier_access_reviews','supplier_automation_rule_versions','supplier_report_schedules','supplier_saved_report_views'] as $table) { DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}"); DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY"); Schema::dropIfExists($table); }
    }
};
