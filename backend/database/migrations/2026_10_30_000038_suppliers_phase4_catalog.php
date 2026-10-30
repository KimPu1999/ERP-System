<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->foreignUuid('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('catalog_reference')->nullable();
            $t->string('supplier_sku');
            $t->string('supplier_name');
            $t->text('description')->nullable();
            $t->string('supplier_barcode')->nullable();
            $t->string('brand')->nullable();
            $t->string('model_number')->nullable();
            $t->string('manufacturer_part_number')->nullable();
            $t->string('packaging_information')->nullable();
            $t->string('purchase_uom')->nullable();
            $t->decimal('minimum_order_quantity', 20, 6)->default(0);
            $t->decimal('order_multiple', 20, 6)->default(0);
            $t->decimal('pack_size', 20, 6)->nullable();
            $t->unsignedInteger('standard_lead_time')->nullable();
            $t->string('lead_time_unit')->nullable();
            $t->string('shipping_origin')->nullable();
            $t->string('availability_status')->default('available');
            $t->text('temporarily_unavailable_reason')->nullable();
            $t->date('expected_availability_date')->nullable();
            $t->date('discontinued_date')->nullable();
            $t->foreignUuid('currency_id')->nullable()->constrained('accounting_currencies')->nullOnDelete();
            $t->decimal('base_purchase_price', 24, 6)->nullable();
            $t->date('price_effective_from')->nullable();
            $t->date('price_effective_to')->nullable();
            $t->string('tax_code')->nullable();
            $t->string('status')->default('active');
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'company_id', 'supplier_id', 'supplier_sku']);
            $t->index(['tenant_id', 'company_id', 'supplier_id', 'status']);
            $t->index(['tenant_id', 'company_id', 'variant_id']);
        });

        Schema::create('supplier_product_mapping_history', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('supplier_product_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('previous_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->foreignUuid('new_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->foreignUuid('changed_by')->constrained('users')->restrictOnDelete();
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('supplier_purchase_price_lists', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('name');
            $t->foreignUuid('currency_id')->nullable()->constrained('accounting_currencies')->nullOnDelete();
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->text('description')->nullable();
            $t->foreignUuid('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'company_id', 'number']);
            $t->index(['tenant_id', 'company_id', 'supplier_id', 'status']);
        });

        Schema::create('supplier_purchase_price_list_items', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('price_list_id')->constrained('supplier_purchase_price_lists')->cascadeOnDelete();
            $t->foreignUuid('supplier_product_id')->constrained('supplier_products')->restrictOnDelete();
            $t->decimal('minimum_quantity', 20, 6)->default(0);
            $t->decimal('maximum_quantity', 20, 6)->nullable();
            $t->decimal('unit_price', 24, 6);
            $t->string('purchase_uom')->nullable();
            $t->string('tax_code')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'price_list_id', 'supplier_product_id']);
        });

        Schema::create('supplier_contracts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('name');
            $t->string('contract_type')->default('purchase');
            $t->foreignUuid('currency_id')->nullable()->constrained('accounting_currencies')->nullOnDelete();
            $t->date('starts_at');
            $t->date('ends_at');
            $t->decimal('spend_ceiling', 24, 6)->nullable();
            $t->string('payment_terms')->nullable();
            $t->text('delivery_terms')->nullable();
            $t->text('renewal_terms')->nullable();
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignUuid('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'company_id', 'number']);
            $t->index(['tenant_id', 'company_id', 'supplier_id', 'status']);
        });

        Schema::create('supplier_contract_versions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('supplier_contract_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'supplier_contract_id', 'version']);
        });

        Schema::table('supplier_documents', function (Blueprint $t): void {
            $t->foreignUuid('supplier_product_id')->nullable()->after('supplier_id')->constrained('supplier_products')->nullOnDelete();
            $t->foreignUuid('supplier_price_list_id')->nullable()->after('supplier_product_id')->constrained('supplier_purchase_price_lists')->nullOnDelete();
            $t->foreignUuid('supplier_contract_id')->nullable()->after('supplier_price_list_id')->constrained('supplier_contracts')->nullOnDelete();
        });
        Schema::table('purchase_order_items', function (Blueprint $t): void {
            $t->foreignUuid('supplier_product_id')->nullable()->after('variant_id')->constrained('supplier_products')->nullOnDelete();
            $t->string('supplier_sku_snapshot')->nullable();
            $t->string('purchase_uom_snapshot')->nullable();
            $t->decimal('minimum_order_quantity_snapshot', 20, 6)->nullable();
            $t->decimal('order_multiple_snapshot', 20, 6)->nullable();
            $t->unsignedInteger('lead_time_snapshot')->nullable();
            $t->string('price_source_type')->nullable();
            $t->uuid('price_source_id')->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['supplier_products','supplier_product_mapping_history','supplier_purchase_price_lists','supplier_purchase_price_list_items','supplier_contracts','supplier_contract_versions'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['supplier_products','supplier_product_mapping_history','supplier_purchase_price_lists','supplier_purchase_price_list_items','supplier_contracts','supplier_contract_versions'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }
        Schema::table('purchase_order_items', function (Blueprint $t): void {
            $t->dropForeign(['supplier_product_id']);
            $t->dropColumn(['supplier_product_id', 'supplier_sku_snapshot', 'purchase_uom_snapshot', 'minimum_order_quantity_snapshot', 'order_multiple_snapshot', 'lead_time_snapshot', 'price_source_type', 'price_source_id']);
        });
        Schema::table('supplier_documents', function (Blueprint $t): void {
            $t->dropForeign(['supplier_product_id']);
            $t->dropForeign(['supplier_price_list_id']);
            $t->dropForeign(['supplier_contract_id']);
            $t->dropColumn(['supplier_product_id', 'supplier_price_list_id', 'supplier_contract_id']);
        });
        Schema::dropIfExists('supplier_contract_versions');
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('supplier_purchase_price_list_items');
        Schema::dropIfExists('supplier_purchase_price_lists');
        Schema::dropIfExists('supplier_product_mapping_history');
        Schema::dropIfExists('supplier_products');
    }
};
