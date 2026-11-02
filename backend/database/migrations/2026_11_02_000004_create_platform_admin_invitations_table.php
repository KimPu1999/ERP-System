<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_admin_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique('padmin_inv_token_uq');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['email', 'status'], 'padmin_inv_email_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admin_invitations');
    }
};
