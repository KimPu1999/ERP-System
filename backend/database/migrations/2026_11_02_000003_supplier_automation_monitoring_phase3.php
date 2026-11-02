<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $addedCompany = false;

        Schema::table('automation_runs', function (Blueprint $table) use (&$addedCompany): void {
            if (! Schema::hasColumn('automation_runs', 'company_id')) {
                $table->uuid('company_id')->nullable();
                $addedCompany = true;
            }
            if (! Schema::hasColumn('automation_runs', 'automation_type')) $table->string('automation_type', 80)->nullable();
            if (! Schema::hasColumn('automation_runs', 'source_type')) $table->string('source_type', 80)->nullable();
            if (! Schema::hasColumn('automation_runs', 'source_id')) $table->uuid('source_id')->nullable();
            if (! Schema::hasColumn('automation_runs', 'source_key')) $table->string('source_key', 180)->nullable();
            if (! Schema::hasColumn('automation_runs', 'trigger_type')) $table->string('trigger_type', 40)->nullable();
            if (! Schema::hasColumn('automation_runs', 'started_at')) $table->timestamp('started_at')->nullable();
            if (! Schema::hasColumn('automation_runs', 'duration_ms')) $table->unsignedInteger('duration_ms')->nullable();
            if (! Schema::hasColumn('automation_runs', 'failure_classification')) $table->string('failure_classification', 80)->nullable();
            if (! Schema::hasColumn('automation_runs', 'correlation_id')) $table->string('correlation_id', 80)->nullable();
        });

        if ($addedCompany) {
            Schema::table('automation_runs', function (Blueprint $table): void {
                $table->foreign('company_id', 'automation_runs_company_fk')->references('id')->on('companies')->nullOnDelete();
            });
        }

        Schema::table('supplier_notification_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_notification_deliveries', 'accepted_at')) $table->timestamp('accepted_at')->nullable();
            if (! Schema::hasColumn('supplier_notification_deliveries', 'confirmed_at')) $table->timestamp('confirmed_at')->nullable();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS automation_runs_scope_idx ON automation_runs (tenant_id, company_id, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS automation_runs_state_idx ON automation_runs (tenant_id, state, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS automation_runs_type_idx ON automation_runs (tenant_id, automation_type, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS supplier_delivery_status_idx ON supplier_notification_deliveries (tenant_id, state, created_at)');

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS automation_runs_scope_idx');
        DB::statement('DROP INDEX IF EXISTS automation_runs_state_idx');
        DB::statement('DROP INDEX IF EXISTS automation_runs_type_idx');
        DB::statement('DROP INDEX IF EXISTS supplier_delivery_status_idx');

        Schema::table('supplier_notification_deliveries', function (Blueprint $table): void {
            $columns = array_values(array_filter(['accepted_at', 'confirmed_at'], fn (string $column): bool => Schema::hasColumn('supplier_notification_deliveries', $column)));
            if ($columns !== []) $table->dropColumn($columns);
        });

        Schema::table('automation_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('automation_runs', 'company_id')) $table->dropForeign('automation_runs_company_fk');
            $columns = array_values(array_filter(['company_id', 'automation_type', 'source_type', 'source_id', 'source_key', 'trigger_type', 'started_at', 'duration_ms', 'failure_classification', 'correlation_id'], fn (string $column): bool => Schema::hasColumn('automation_runs', $column)));
            if ($columns !== []) $table->dropColumn($columns);
        });
    }
};
