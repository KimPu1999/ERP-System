<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('role_assignments', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('role_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete(); $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete(); $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete(); $table->timestamps(); $table->index(['tenant_id', 'user_id']); }); Schema::create('permission_role', function (Blueprint $table): void { $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('role_id')->constrained()->cascadeOnDelete(); $table->primary(['permission_id', 'role_id']); }); }
    public function down(): void { Schema::dropIfExists('permission_role'); Schema::dropIfExists('role_assignments'); }
};
