<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This is a forward repair for environments where 000035 was recorded in
     * the migration ledger but its phase-2 table was not created. Every table
     * operation is guarded so an already deployed schema is never duplicated.
     */
    public function up(): void
    {
        if (! Schema::hasTable('supplier_onboarding_applications')) {
            Schema::create('supplier_onboarding_applications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('assigned_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
                $table->string('number');
                $table->string('application_type')->default('new_supplier_onboarding');
                $table->string('status')->default('draft');
                $table->string('approval_status')->default('not_submitted');
                $table->unsignedInteger('version')->default(1);
                $table->json('snapshot')->nullable();
                $table->text('notes')->nullable();
                $table->text('change_request_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'number']);
                $table->index(['tenant_id', 'company_id', 'status']);
                $table->index(['tenant_id', 'supplier_id', 'version']);
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['supplier_onboarding_applications', 'supplier_document_requirements', 'supplier_documents', 'supplier_document_reviews'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['supplier_onboarding_applications', 'supplier_document_requirements', 'supplier_documents', 'supplier_document_reviews'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
