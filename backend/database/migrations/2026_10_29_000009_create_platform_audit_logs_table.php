<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('platform_audit_logs', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->string('auditable_type')->nullable(); $table->uuid('auditable_id')->nullable(); $table->jsonb('metadata')->nullable(); $table->ipAddress('ip_address')->nullable(); $table->timestamps(); $table->index(['tenant_id', 'created_at']); }); }
    public function down(): void { Schema::dropIfExists('platform_audit_logs'); }
};
