<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('variant_id')->constrained('product_variants')->cascadeOnDelete(); $table->foreignUuid('location_id')->constrained('warehouse_locations')->cascadeOnDelete(); $table->decimal('quantity', 20, 6)->default(0); $table->decimal('reserved_quantity', 20, 6)->default(0); $table->timestamps(); $table->unique(['variant_id', 'location_id']); });
        Schema::create('stock_movements', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('variant_id')->constrained('product_variants'); $table->foreignUuid('location_id')->constrained('warehouse_locations'); $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('movement_type'); $table->decimal('quantity', 20, 6); $table->decimal('unit_cost', 20, 6)->default(0); $table->string('reference_type')->nullable(); $table->uuid('reference_id')->nullable(); $table->string('idempotency_key', 100); $table->timestamp('posted_at'); $table->timestamps(); $table->index(['tenant_id', 'variant_id', 'location_id', 'posted_at']); $table->unique(['tenant_id', 'idempotency_key']); });
    }
    public function down(): void { Schema::dropIfExists('stock_movements'); Schema::dropIfExists('inventory_balances'); }
};
