<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 — Continuous Improvement Center tables.
 *
 * Forward-only, additive: feedback, feature requests, roadmap, defects,
 * rollout governance, SLOs, satisfaction, maturity and governance reviews.
 * Every tenant-scoped table gets ENABLE ROW LEVEL SECURITY with the same
 * tenant/platform policy used by the Phase 12 support tables.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feedback_number', 40)->unique();
            $table->string('category', 40); // bug|feature_request|usability|performance|documentation|other
            $table->string('module', 80)->nullable();
            $table->string('subject', 180);
            $table->text('message');
            $table->string('status', 32)->default('submitted'); // submitted|under_review|needs_information|accepted|planned|declined|resolved|closed
            $table->jsonb('attachments')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->foreignUuid('duplicate_of_id')->nullable();
            $table->foreignUuid('support_ticket_id')->nullable();
            $table->foreignUuid('submitted_by')->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('web_form');
            $table->unsignedTinyInteger('satisfaction_score')->nullable(); // voluntary 1..5, set by the submitter only
            $table->text('satisfaction_comment')->nullable();
            $table->text('resolution_outcome')->nullable();
            $table->foreignUuid('resolution_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'category']);
            $table->index('content_hash');
        });

        Schema::create('feedback_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('feedback_id')->constrained('product_feedback')->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 20); // business | platform
            $table->string('visibility', 20); // public | internal (internal = platform reviewers only)
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['feedback_id', 'created_at']);
        });

        Schema::create('feedback_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('feedback_id')->constrained('product_feedback')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['feedback_id', 'created_at']);
        });

        Schema::create('feature_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('request_key', 40)->unique();
            $table->string('title', 180);
            $table->text('description');
            $table->string('module', 80)->nullable();
            $table->jsonb('origin')->nullable(); // [{type: feedback|ticket|manual, ref}]
            $table->text('business_impact')->nullable();
            $table->string('requester_reference', 120)->nullable();
            $table->string('priority', 20)->default('medium'); // critical|high|medium|low
            $table->string('effort_estimate', 40)->nullable(); // null => "not estimated"
            $table->string('risk', 20)->nullable(); // low|medium|high
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('new'); // new|triaged|planned|in_development|in_testing|released|deferred|rejected
            $table->foreignUuid('target_release_id')->nullable(); // release_records id (added by next migration)
            $table->text('target_release_notes')->nullable();
            $table->jsonb('linked_issues')->nullable(); // development issue references
            $table->jsonb('release_evidence')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('module');
        });

        Schema::create('feature_request_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('feature_requests')->cascadeOnDelete();
            $table->string('event', 40);
            $table->jsonb('payload')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index('request_id');
        });

        Schema::create('roadmap_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('module', 80)->nullable();
            $table->string('source_type', 30)->default('initiative'); // feature_request|defect|initiative
            $table->uuid('source_id')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('backlog'); // backlog|planned|in_progress|in_testing|ready_for_release|released
            $table->jsonb('dependencies')->nullable(); // list of roadmap item ids
            $table->foreignUuid('target_release_id')->nullable();
            $table->string('target_quarter', 20)->nullable(); // only after approval
            $table->text('completion_criteria')->nullable();
            $table->jsonb('release_evidence')->nullable();
            $table->string('visibility', 20)->default('internal'); // internal | customer_visible
            $table->text('customer_summary')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('module');
        });

        Schema::create('defect_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('defect_number', 40)->unique();
            $table->string('title', 180);
            $table->string('module', 80)->nullable();
            $table->string('environment', 30)->default('staging'); // local|staging|production
            $table->string('severity', 8); // P0|P1|P2|P3
            $table->text('reproduction_steps')->nullable();
            $table->text('expected_result')->nullable();
            $table->text('actual_result')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->text('root_cause')->nullable();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open'); // open|triaged|assigned|in_progress|fixed|ready_for_retest|verified|closed
            $table->string('related_change_ref', 120)->nullable();
            $table->string('related_test_ref', 120)->nullable();
            $table->foreignUuid('fix_release_id')->nullable();
            $table->string('fix_version', 60)->nullable();
            $table->string('retest_result', 20)->nullable(); // pass|fail|blocked
            $table->foreignUuid('retest_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retest_verified_at')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->uuid('source_feedback_id')->nullable();
            $table->uuid('source_incident_id')->nullable();
            $table->uuid('source_ticket_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
            $table->index('module');
        });

        Schema::create('defect_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('defect_id')->constrained('defect_records')->cascadeOnDelete();
            $table->string('event', 40);
            $table->jsonb('payload')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index('defect_id');
        });

        Schema::create('feature_rollouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('feature_flag_id')->constrained('feature_flags')->cascadeOnDelete();
            $table->string('stage', 32)->default('development'); // development|internal_cohort|pilot|wider_rollout|general_availability|disabled|rolled_back
            $table->string('scope', 20)->default('tenant'); // global|tenant|user
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('audience')->nullable(); // approved tenant/cohort/user ids or percentage config
            $table->unsignedTinyInteger('rollout_percentage')->nullable(); // only where safely supported
            $table->string('approval_status', 20)->default('none'); // none|pending|approved
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('review_at')->nullable(); // flag review/expiration date
            $table->string('monitoring_link', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('feature_flag_id');
        });

        Schema::create('feature_rollout_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rollout_id')->constrained('feature_rollouts')->cascadeOnDelete();
            $table->string('event', 40);
            $table->jsonb('payload')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index('rollout_id');
        });

        Schema::create('service_level_objectives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->string('sli_type', 30); // availability|success_ratio|latency|queue_completion
            $table->string('metric_source', 80); // monitoring service/check key or route pattern
            $table->decimal('target', 5, 3); // e.g. 99.900 (% availability) or 95.000 (% latency-under-threshold)
            $table->unsignedSmallInteger('window_days')->default(30);
            $table->jsonb('criteria')->nullable(); // e.g. latency threshold ms, filters
            $table->string('status', 20)->default('targets_defined'); // targets_defined|meeting|at_risk|violated (evaluated on read)
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('satisfaction_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('context', 40); // product_feedback|support_ticket|release_note
            $table->uuid('context_id')->nullable();
            $table->unsignedTinyInteger('score'); // voluntary 1..5
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['tenant_id', 'context', 'submitted_at']);
            $table->unique(['user_id', 'context', 'context_id']); // one rating per user per subject
        });

        Schema::create('maturity_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('dimension', 40); // functional_reliability|security|data_integrity|tenant_isolation|test_coverage|accessibility|localization|documentation|operational_readiness|performance|release_quality|customer_support
            $table->string('period', 20); // e.g. 2026-Q4
            $table->string('level', 20); // not_assessed|initial|developing|managed|optimized
            $table->text('evidence');
            $table->text('gaps')->nullable();
            $table->text('improvement_actions')->nullable();
            $table->foreignUuid('reviewer_id')->constrained('users');
            $table->timestamp('assessed_at');
            $table->timestamps();
            $table->unique(['dimension', 'period']);
            $table->index('period');
        });

        Schema::create('improvement_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('cadence', 20); // daily|weekly|biweekly|monthly|quarterly
            $table->string('title', 180);
            $table->text('findings')->nullable();
            $table->text('decisions')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('next_action')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->date('review_date');
            $table->jsonb('evidence')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['cadence', 'review_date']);
        });

        foreach (['product_feedback', 'feedback_notes', 'feedback_status_history', 'satisfaction_responses'] as $table) {
            $this->applyRls($table);
        }
    }

    private function applyRls(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        // FORCE is required so the table-owning application role cannot bypass
        // the policy (Phase 14 audit finding — see 2026_11_04_000001).
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
    }

    public function down(): void
    {
        foreach (['improvement_reviews', 'maturity_assessments', 'satisfaction_responses', 'service_level_objectives', 'feature_rollout_events', 'feature_rollouts', 'defect_events', 'defect_records', 'roadmap_items', 'feature_request_events', 'feature_requests', 'feedback_status_history', 'feedback_notes', 'product_feedback'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
