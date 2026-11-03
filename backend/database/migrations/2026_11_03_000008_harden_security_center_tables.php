<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->string('category')->default('system')->index();
            $table->string('result')->default('success')->index();
            $table->string('request_id')->nullable()->index();
            $table->string('actor_type')->default('user');
            $table->string('resource_type')->nullable()->index();
            $table->jsonb('before_state')->nullable();
            $table->jsonb('after_state')->nullable();
        });

        Schema::table('security_events', function (Blueprint $table): void {
            $table->string('category')->default('authentication')->index();
            $table->string('result')->default('success')->index();
            $table->string('request_id')->nullable()->index();
        });

        if (! Schema::hasTable('security_settings')) {
            Schema::create('security_settings', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->jsonb('value');
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
        Schema::table('security_events', function (Blueprint $table): void {
            foreach (['category', 'result', 'request_id'] as $column) {
                if (Schema::hasColumn('security_events', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            foreach (['category', 'result', 'request_id', 'actor_type', 'resource_type', 'before_state', 'after_state'] as $column) {
                if (Schema::hasColumn('platform_audit_logs', $column)) $table->dropColumn($column);
            }
        });
    }
};
