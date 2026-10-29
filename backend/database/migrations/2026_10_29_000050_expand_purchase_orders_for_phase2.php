<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->date('order_date')->nullable()->after('number');
            $table->string('supplier_reference')->nullable()->after('expected_at');
            $table->string('payment_terms')->nullable()->after('supplier_reference');
            $table->foreignUuid('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->nullOnDelete();
            $table->text('delivery_address')->nullable()->after('payment_terms');
            $table->decimal('subtotal', 24, 6)->nullable()->after('base_total');
            $table->decimal('discount_total', 24, 6)->nullable()->after('subtotal');
            $table->decimal('tax_total', 24, 6)->nullable()->after('discount_total');
            $table->decimal('shipping_total', 24, 6)->nullable()->after('tax_total');
            $table->decimal('grand_total', 24, 6)->nullable()->after('shipping_total');
            $table->index(['tenant_id', 'company_id', 'order_date']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('variant_id');
            $table->string('discount_type')->default('amount')->after('unit_price');
            $table->decimal('discount_amount', 24, 6)->default(0)->after('discount_type');
            $table->decimal('tax_rate', 12, 6)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 24, 6)->default(0)->after('tax_rate');
            $table->decimal('line_subtotal', 24, 6)->nullable()->after('tax_amount');
            $table->decimal('line_total', 24, 6)->nullable()->after('line_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropColumn(['description', 'discount_type', 'discount_amount', 'tax_rate', 'tax_amount', 'line_subtotal', 'line_total']);
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex(['tenant_id', 'company_id', 'order_date']);
            $table->dropColumn(['order_date', 'supplier_reference', 'payment_terms', 'warehouse_id', 'delivery_address', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total']);
        });
    }
};
