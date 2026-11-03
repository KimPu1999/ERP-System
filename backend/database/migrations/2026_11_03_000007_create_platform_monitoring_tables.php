<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitoring_observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('service', 60);
            $table->string('check_key', 100);
            $table->string('status', 20);
            $table->timestamp('observed_at');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('detail')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['service', 'check_key', 'observed_at']);
        });

        Schema::create('monitoring_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('fingerprint', 128)->unique();
            $table->string('service', 60);
            $table->string('severity', 20);
            $table->string('title', 180);
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->timestamp('first_observed_at');
            $table->timestamp('last_observed_at');
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'last_observed_at']);
            $table->index(['service', 'status']);
        });

        Schema::create('monitoring_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->jsonb('affected_services')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'started_at']);
        });

        Schema::create('monitoring_incident_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('incident_id')->constrained('monitoring_incidents')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['incident_id', 'created_at']);
        });

        Schema::create('monitoring_error_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('fingerprint', 128)->unique();
            $table->string('exception_class', 180);
            $table->text('message');
            $table->string('route', 180)->nullable();
            $table->string('severity', 20)->default('error');
            $table->unsignedBigInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['severity', 'last_seen_at']);
            $table->index(['route', 'last_seen_at']);
        });

        Schema::create('monitoring_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('setting_key', 80)->unique();
            $table->boolean('enabled')->default(true);
            $table->jsonb('thresholds')->nullable();
            $table->jsonb('notification_preferences')->nullable();
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->unsignedSmallInteger('refresh_seconds')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_settings');
        Schema::dropIfExists('monitoring_error_groups');
        Schema::dropIfExists('monitoring_incident_events');
        Schema::dropIfExists('monitoring_incidents');
        Schema::dropIfExists('monitoring_alerts');
        Schema::dropIfExists('monitoring_observations');
    }
};
