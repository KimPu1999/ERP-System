<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('departments', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete(); $table->string('name'); $table->string('code'); $table->timestamps(); $table->unique(['company_id', 'code']); }); }
    public function down(): void { Schema::dropIfExists('departments'); }
};
