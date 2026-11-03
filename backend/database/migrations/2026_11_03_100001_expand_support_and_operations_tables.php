<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 12 — support ticket lifecycle and operations records.
 *
 * Forward-only: adds columns/tables only; no destructive changes and no data resets.
 * Legacy support_tickets rows are backfilled with generated ticket numbers and the
 * new `status` lifecycle (the old `state` column is kept but no longer authoritative).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_sla_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('priority', 20)->nullable();
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->jsonb('business_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('ticket_number', 40)->nullable()->after('tenant_id');
            $table->string('category', 40)->default('general')->after('subject');
            $table->foreignUuid('assigned_to')->nullable()->after('state')->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open')->after('assigned_to');
            $table->foreignUuid('sla_policy_id')->nullable()->after('status')->constrained('support_sla_policies')->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('sla_breach_log')->nullable();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'priority']);
            $table->index('ticket_number');
        });

        // Legacy rows: generate ticket numbers and normalize the state→status transfer.
        $legacy = DB::table('support_tickets')->whereNull('ticket_number')->orderBy('created_at')->get(['id', 'state']);
        foreach ($legacy as $row) {
            DB::table('support_tickets')->where('id', $row->id)->update([
                'ticket_number' => 'TKT-LEGACY-'.strtoupper(substr(str_replace('-', '', $row->id), 0, 10)),
                'status' => $row->state === 'resolved' || $row->state === 'closed' ? $row->state : 'open',
            ]);
        }
        DB::statement('ALTER TABLE support_tickets ALTER COLUMN ticket_number SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS support_tickets_number_unique ON support_tickets (ticket_number)');

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 20); // customer | support
            $table->string('visibility', 20); // public | internal
            $table->text('body');
            $table->jsonb('attachments')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('support_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 200);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('created_at');
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->text('scope')->nullable();
            $table->text('impact_description')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->string('status', 20)->default('planned');
            $table->boolean('notify_businesses')->default(true);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('operational_cost_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('occurred_on');
            $table->string('provider', 80);
            $table->string('category', 60);
            $table->text('description')->nullable();
            $table->decimal('amount', 16, 4);
            $table->char('currency', 3);
            $table->string('cost_type', 20)->default('actual'); // actual | estimate | forecast
            $table->string('source', 30)->default('manual'); // manual | provider_export
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index(['occurred_on', 'currency']);
            $table->index(['provider', 'occurred_on']);
        });

        Schema::create('backup_verification_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('backup_taken_at');
            $table->string('backup_type', 30); // pg_dump | base_backup | wal | files
            $table->string('storage_location', 255)->nullable();
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_status', 20)->default('registered'); // registered | restore_verified | failed
            $table->timestamp('restore_tested_at')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index('backup_taken_at');
        });

        Schema::create('release_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('version', 60);
            $table->string('environment', 30);
            $table->string('status', 30)->default('deployed'); // deployed | rolled_back | aborted
            $table->text('release_notes')->nullable();
            $table->text('known_issues')->nullable();
            $table->text('rollback_reference')->nullable();
            $table->timestamp('deployed_at');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index(['environment', 'deployed_at']);
        });

        $this->applyRls('support_messages');
        $this->applyRls('support_attachments');
    }

    private function applyRls(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
    }

    public function down(): void
    {
        foreach (['release_records', 'backup_verification_records', 'operational_cost_records', 'maintenance_windows', 'support_attachments', 'support_messages'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP INDEX IF EXISTS support_tickets_number_unique');
        foreach (['support_tickets_status_priority_index', 'support_tickets_tenant_id_created_at_index', 'support_tickets_tenant_id_status_index'] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
        Schema::dropIfExists('support_sla_policies');
        foreach (['sla_breach_log', 'closed_at', 'resolved_at', 'first_response_at', 'resolution_due_at', 'first_response_due_at', 'sla_policy_id', 'status', 'assigned_to', 'priority_days', 'category', 'ticket_number'] as $column) {
            DB::statement("ALTER TABLE support_tickets DROP COLUMN IF EXISTS {$column}");
        }
        foreach (['support_tickets_resolved_by_foreign', 'support_tickets_sla_policy_id_foreign', 'support_tickets_assigned_to_foreign'] as $constraint) {
            DB::statement("ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS {$constraint}");
        }
        foreach (['resolved_by', 'sla_policy_id', 'assigned_to'] as $column) {
            DB::statement("ALTER TABLE support_tickets DROP COLUMN IF EXISTS {$column}");
        }
    }
};
