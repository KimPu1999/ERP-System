<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('invitations', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('inviter_user_id')->constrained('users')->cascadeOnDelete(); $table->foreignUuid('role_id')->nullable()->constrained()->nullOnDelete(); $table->string('email'); $table->string('token_hash', 64)->unique(); $table->timestamp('expires_at'); $table->timestamp('accepted_at')->nullable(); $table->timestamps(); $table->index(['tenant_id', 'email']); }); }
    public function down(): void { Schema::dropIfExists('invitations'); }
};
