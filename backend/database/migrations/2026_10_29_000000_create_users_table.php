<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('users', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->string('name'); $table->string('email')->unique(); $table->timestamp('email_verified_at')->nullable(); $table->string('password'); $table->string('status')->default('active'); $table->timestamp('suspended_at')->nullable(); $table->boolean('is_platform_super_admin')->default(false); $table->text('mfa_secret_encrypted')->nullable(); $table->timestamp('mfa_enabled_at')->nullable(); $table->jsonb('mfa_recovery_codes')->nullable(); $table->rememberToken(); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('users'); }
};
