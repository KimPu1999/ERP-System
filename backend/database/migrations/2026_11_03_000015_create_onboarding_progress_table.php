<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('step_key', 80);
            $table->string('status', 24)->default('not_started');
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->uuid('source_id')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'step_key']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
    }
};
