<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->date('receipt_date')->nullable()->after('number');
            $table->string('supplier_delivery_number')->nullable()->after('receipt_date');
            $table->string('reference_number')->nullable()->after('supplier_delivery_number');
            $table->text('receiving_notes')->nullable()->after('reference_number');
            $table->foreignUuid('posted_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->index(['tenant_id', 'company_id', 'branch_id', 'state', 'receipt_date'], 'goods_receipts_scope_status_date_idx');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->decimal('accepted_quantity', 20, 6)->default(0)->after('quantity');
            $table->decimal('rejected_quantity', 20, 6)->default(0)->after('accepted_quantity');
            $table->decimal('damaged_quantity', 20, 6)->default(0)->after('rejected_quantity');
            $table->string('inspection_status')->default('accepted')->after('damaged_quantity');
            $table->text('inspection_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('damage_reason')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->index(['tenant_id', 'serial_number'], 'goods_receipt_items_serial_idx');
            $table->index(['tenant_id', 'batch_number'], 'goods_receipt_items_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropIndex('goods_receipt_items_serial_idx');
            $table->dropIndex('goods_receipt_items_batch_idx');
            $table->dropColumn(['accepted_quantity', 'rejected_quantity', 'damaged_quantity', 'inspection_status', 'inspection_notes', 'rejection_reason', 'damage_reason', 'batch_number', 'serial_number', 'manufacturing_date', 'expiry_date']);
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropIndex('goods_receipts_scope_status_date_idx');
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['posted_by']);
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['company_id', 'branch_id', 'warehouse_id', 'receipt_date', 'supplier_delivery_number', 'reference_number', 'receiving_notes', 'posted_by', 'reversed_at', 'reversed_by', 'reversal_reason']);
        });
    }
};
