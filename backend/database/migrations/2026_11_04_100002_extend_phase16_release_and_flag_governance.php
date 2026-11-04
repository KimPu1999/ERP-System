<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 — additive governance columns for the existing Phase 12
 * `release_records` and the previously unused `feature_flags` tables.
 *
 * Legacy statuses (deployed|rolled_back|aborted) remain valid; the
 * improvement center adds a QA/approval lifecycle on top. Nothing here
 * resets data or makes flags controlling: feature flags remain rollout
 * metadata only, never authorization.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('release_records', function (Blueprint $table): void {
            DB::statement('ALTER TABLE release_records ALTER COLUMN deployed_at DROP NOT NULL'); // Phase 16 lifecycle: drafts are pre-deployment
            $table->string('release_id', 60)->nullable(); // human release identifier e.g. REL-2026.11-01
            $table->jsonb('linked_features')->nullable(); // feature request keys
            $table->jsonb('linked_fixes')->nullable(); // defect numbers
            $table->text('release_notes_my')->nullable(); // Burmese customer notes
            $table->jsonb('release_notes_sections')->nullable(); // structured: new_features|improvements|bug_fixes|security_notices|known_limitations|required_actions
            $table->string('audience', 20)->default('platform'); // platform|business|both
            $table->boolean('customer_visible')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('approval_state', 20)->default('none'); // none|pending|approved
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('deployment_ref', 255)->nullable(); // CI/CD run or deploy log reference
            $table->index(['environment', 'status']);
            $table->index('customer_visible');
        });

        Schema::table('feature_flags', function (Blueprint $table): void {
            $table->string('scope', 20)->default('tenant'); // global|tenant|user (rollout scope, NOT authorization)
            $table->string('rollout_stage', 32)->nullable(); // mirrors controlled rollout workflow stage
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_at')->nullable(); // mandatory review/expiration date
            $table->boolean('is_sensitive')->default(false); // confidential security features excluded from anything customer visible
        });
    }

    public function down(): void
    {
        foreach (['release_records_environment_status_index', 'release_records_customer_visible_index'] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
        DB::statement('ALTER TABLE release_records DROP CONSTRAINT IF EXISTS release_records_approved_by_foreign');
        foreach (['deployment_ref', 'approved_at', 'approval_state', 'published_at', 'customer_visible', 'audience', 'release_notes_sections', 'release_notes_my', 'linked_fixes', 'linked_features', 'release_id'] as $column) {
            DB::statement("ALTER TABLE release_records DROP COLUMN IF EXISTS {$column}");
        }
        DB::statement('ALTER TABLE feature_flags DROP CONSTRAINT IF EXISTS feature_flags_owner_id_foreign');
        DB::statement('ALTER TABLE release_records ALTER COLUMN deployed_at SET NOT NULL'); // legacy constraint restoration for rollback path
        foreach (['is_sensitive', 'review_at', 'owner_id', 'rollout_stage', 'scope'] as $column) {
            DB::statement("ALTER TABLE feature_flags DROP COLUMN IF EXISTS {$column}");
        }
    }
};
