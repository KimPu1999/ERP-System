<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchasing_settings', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUuid('default_branch_id')->nullable()->constrained('branches')->nullOnDelete(); $t->foreignUuid('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete(); $t->foreignUuid('default_currency_id')->nullable()->constrained('accounting_currencies')->nullOnDelete(); $t->foreignUuid('purchasing_department_id')->nullable()->constrained('departments')->nullOnDelete(); $t->foreignUuid('default_purchasing_contact_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('default_payment_terms')->nullable(); $t->string('default_tax_policy')->nullable(); $t->text('default_po_notes')->nullable(); $t->text('default_delivery_instructions')->nullable(); $t->unsignedSmallInteger('po_expiry_days')->nullable(); $t->string('document_language', 10)->default('en');
            $t->boolean('receiving_approval_required')->default(false); $t->decimal('over_receiving_tolerance', 20, 6)->default(0); $t->string('over_receiving_tolerance_unit', 20)->default('percent'); $t->boolean('partial_receiving_allowed')->default(true); $t->boolean('quality_inspection_required')->default(false); $t->string('damaged_goods_handling')->default('quarantine'); $t->boolean('quarantine_required')->default(false); $t->boolean('batch_tracking_required')->default(false); $t->boolean('serial_tracking_required')->default(false); $t->boolean('expiry_validation')->default(false); $t->boolean('location_validation')->default(true);
            $t->string('matching_type')->default('three_way'); $t->decimal('price_tolerance', 20, 6)->default(0); $t->string('price_tolerance_unit', 20)->default('percent'); $t->decimal('quantity_tolerance', 20, 6)->default(0); $t->string('quantity_tolerance_unit', 20)->default('percent'); $t->decimal('tax_tolerance', 20, 6)->default(0); $t->string('tax_tolerance_unit', 20)->default('amount'); $t->string('matching_review_permission')->nullable();
            $t->unsignedInteger('version')->default(1); $t->timestamps(); $t->unique(['tenant_id','company_id','branch_id']); $t->index(['tenant_id','company_id']);
        });
        Schema::create('purchasing_numbering_policies', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $t->string('document_type'); $t->string('prefix', 40); $t->boolean('include_year')->default(true); $t->boolean('include_branch_code')->default(false); $t->unsignedSmallInteger('sequence_length')->default(6); $t->string('sequence_scope')->default('company'); $t->string('reset_policy')->default('fiscal_year'); $t->unsignedBigInteger('next_sequence')->default(1); $t->timestamps(); $t->unique(['tenant_id','company_id','document_type']);
        });
        Schema::table('automation_rules', function (Blueprint $t): void { $t->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete(); $t->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete(); $t->string('rule_type')->nullable(); $t->string('frequency')->nullable(); $t->string('timezone')->nullable(); $t->unsignedSmallInteger('reminder_delay_minutes')->nullable(); $t->jsonb('recipients')->nullable(); $t->jsonb('channels')->nullable(); $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->index(['tenant_id','company_id','is_active']); });
        Schema::create('purchasing_notification_preferences', function (Blueprint $t): void { $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete(); $t->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete(); $t->string('event_type'); $t->string('channel')->default('in_app'); $t->string('frequency')->default('immediate'); $t->boolean('is_enabled')->default(true); $t->unsignedSmallInteger('reminder_interval_minutes')->nullable(); $t->timestamps(); $t->unique(['tenant_id','company_id','user_id','event_type','channel']);
        });
        DB::statement('CREATE UNIQUE INDEX purchasing_settings_company_default_unique ON purchasing_settings (tenant_id, company_id) WHERE branch_id IS NULL');
        foreach (['purchasing_settings', 'purchasing_numbering_policies', 'purchasing_notification_preferences'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
        }
    }
    public function down(): void
    {
        foreach (['purchasing_settings', 'purchasing_numbering_policies', 'purchasing_notification_preferences'] as $table) { DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}"); DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY"); }
        DB::statement('DROP INDEX IF EXISTS purchasing_settings_company_default_unique'); Schema::dropIfExists('purchasing_notification_preferences'); Schema::table('automation_rules', function (Blueprint $t): void { $t->dropColumn(['company_id','branch_id','rule_type','frequency','timezone','reminder_delay_minutes','recipients','channels','created_by']); }); Schema::dropIfExists('purchasing_numbering_policies'); Schema::dropIfExists('purchasing_settings');
    }
};
