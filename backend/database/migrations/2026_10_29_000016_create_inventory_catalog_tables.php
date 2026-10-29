<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->uuid('parent_id')->nullable(); $table->string('name'); $table->string('slug'); $table->timestamps(); $table->unique(['tenant_id', 'slug']); });
        Schema::table('product_categories', function (Blueprint $table): void { $table->foreign('parent_id')->references('id')->on('product_categories')->nullOnDelete(); });
        Schema::create('brands', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('slug'); $table->timestamps(); $table->unique(['tenant_id', 'slug']); });
        Schema::create('units_of_measure', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('code'); $table->unsignedSmallInteger('decimal_places')->default(3); $table->timestamps(); $table->unique(['tenant_id', 'code']); });
        Schema::create('products', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('category_id')->nullable()->constrained('product_categories')->nullOnDelete(); $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete(); $table->string('name'); $table->string('slug'); $table->text('description')->nullable(); $table->string('status')->default('active'); $table->timestamps(); $table->unique(['tenant_id', 'slug']); });
        Schema::create('product_variants', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('product_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('unit_id')->constrained('units_of_measure'); $table->string('name')->nullable(); $table->string('sku'); $table->string('internal_barcode', 32); $table->string('gs1_identifier', 32)->nullable(); $table->decimal('cost', 20, 6)->default(0); $table->decimal('price', 20, 6)->default(0); $table->jsonb('attributes')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['tenant_id', 'sku']); $table->unique(['tenant_id', 'internal_barcode']); $table->unique(['tenant_id', 'gs1_identifier']); });
        Schema::create('product_media', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('product_id')->nullable()->constrained()->cascadeOnDelete(); $table->foreignUuid('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete(); $table->string('path'); $table->string('mime_type'); $table->string('kind')->default('image'); $table->timestamps(); $table->index(['tenant_id', 'product_id']); });
    }
    public function down(): void { foreach (['product_media', 'product_variants', 'products', 'units_of_measure', 'brands', 'product_categories'] as $table) Schema::dropIfExists($table); }
};
