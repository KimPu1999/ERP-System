<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('approval_definitions', function (Blueprint $table): void {
            $table->string('currency_code', 3)->nullable()->after('scenario');
            $table->date('effective_from')->nullable()->after('currency_code');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->unsignedInteger('version')->default(1)->after('effective_to');
        });
        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('maximum_amount');
        });
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->unsignedInteger('approval_cycle')->default(1)->after('document_id');
            $table->string('currency_code', 3)->nullable()->after('amount');
            $table->jsonb('policy_snapshot')->nullable()->after('currency_code');
            $table->timestamp('submitted_at')->nullable()->after('completed_at');
            $table->foreignUuid('previous_request_id')->nullable()->after('submitted_at')->constrained('approval_requests')->nullOnDelete();
            $table->index(['tenant_id', 'document_type', 'document_id', 'approval_cycle']);
        });
        Schema::table('approval_actions', function (Blueprint $table): void {
            $table->string('previous_status')->nullable()->after('comment');
            $table->string('new_status')->nullable()->after('previous_status');
            $table->unsignedInteger('policy_version')->nullable()->after('new_status');
            $table->uuid('request_correlation_id')->nullable()->after('policy_version');
            $table->index(['tenant_id', 'approval_request_id', 'level']);
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignUuid('approval_request_id')->nullable()->after('approval_cycle')->constrained('approval_requests')->nullOnDelete();
            $table->unsignedInteger('approval_cycle')->default(0)->after('approval_status');
            $table->string('approval_status')->default('not_submitted')->after('state');
            $table->index(['tenant_id', 'company_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropForeign(['approval_request_id']);
            $table->dropIndex(['tenant_id', 'company_id', 'approval_status']);
            $table->dropColumn(['approval_request_id', 'approval_cycle', 'approval_status']);
        });
        Schema::table('approval_actions', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'approval_request_id', 'level']);
            $table->dropColumn(['previous_status', 'new_status', 'policy_version', 'request_correlation_id']);
        });
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropForeign(['previous_request_id']);
            $table->dropIndex(['tenant_id', 'document_type', 'document_id', 'approval_cycle']);
            $table->dropColumn(['approval_cycle', 'currency_code', 'policy_snapshot', 'submitted_at', 'previous_request_id']);
        });
        Schema::table('approval_steps', fn (Blueprint $table): mixed => $table->dropColumn('is_active'));
        Schema::table('approval_definitions', fn (Blueprint $table): mixed => $table->dropColumn(['currency_code', 'effective_from', 'effective_to', 'version']));
    }
};
