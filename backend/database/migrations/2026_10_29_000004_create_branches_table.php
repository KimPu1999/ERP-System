<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('branches', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('code'); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['company_id', 'code']); $table->index(['tenant_id', 'company_id']); }); }
    public function down(): void { Schema::dropIfExists('branches'); }
};
