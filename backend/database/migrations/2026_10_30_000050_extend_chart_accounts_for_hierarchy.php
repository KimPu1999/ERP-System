<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chart_accounts', function (Blueprint $table): void {
            $table->string('subtype')->nullable()->after('type');
            $table->foreignUuid('parent_id')->nullable()->after('company_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->boolean('posting_allowed')->default(true)->after('is_control');
            $table->boolean('is_system')->default(false)->after('posting_allowed');
            $table->foreignUuid('created_by')->nullable()->after('is_system')->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'company_id', 'parent_id']);
            $table->index(['tenant_id', 'company_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('chart_accounts', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['chart_accounts_tenant_id_company_id_parent_id_index']);
            $table->dropIndex(['chart_accounts_tenant_id_company_id_type_is_active_index']);
            $table->dropColumn(['subtype', 'parent_id', 'description', 'posting_allowed', 'is_system', 'created_by', 'updated_by']);
        });
    }
};
