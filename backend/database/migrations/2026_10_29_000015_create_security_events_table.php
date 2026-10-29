<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('security_events', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete(); $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete(); $table->string('event'); $table->ipAddress('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->jsonb('metadata')->nullable(); $table->timestamps(); $table->index(['user_id', 'created_at']); }); }
    public function down(): void { Schema::dropIfExists('security_events'); }
};
