<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete(); $table->string('name'); $table->string('code'); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['tenant_id', 'code']); });
        Schema::create('warehouse_locations', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete(); $table->uuid('parent_id')->nullable(); $table->string('name'); $table->string('code'); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['warehouse_id', 'code']); });
        Schema::table('warehouse_locations', function (Blueprint $table): void { $table->foreign('parent_id')->references('id')->on('warehouse_locations')->nullOnDelete(); });
        Schema::create('inventory_settings', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete(); $table->boolean('allow_negative_stock')->default(false); $table->timestamps(); });
    }
    public function down(): void { foreach (['inventory_settings', 'warehouse_locations', 'warehouses'] as $table) Schema::dropIfExists($table); }
};
