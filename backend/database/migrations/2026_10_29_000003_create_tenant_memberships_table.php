<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('tenant_memberships', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); $table->string('status')->default('active'); $table->timestamps(); $table->unique(['tenant_id', 'user_id']); }); }
    public function down(): void { Schema::dropIfExists('tenant_memberships'); }
};
