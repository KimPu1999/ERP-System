<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_returns', 'company_id')) $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('purchase_returns', 'branch_id')) $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('purchase_returns', 'goods_receipt_id')) $table->foreignUuid('goods_receipt_id')->nullable()->after('purchase_order_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('purchase_returns', 'return_date')) $table->date('return_date')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'reason')) $table->string('reason')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'reason_detail')) $table->string('reason_detail')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'priority')) $table->string('priority')->default('normal');
            if (! Schema::hasColumn('purchase_returns', 'requested_resolution')) $table->string('requested_resolution')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'internal_reference')) $table->string('internal_reference')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'notes')) $table->text('notes')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'approval_status')) $table->string('approval_status')->default('draft');
            if (! Schema::hasColumn('purchase_returns', 'shipment_status')) $table->string('shipment_status')->default('not_prepared');
            if (! Schema::hasColumn('purchase_returns', 'supplier_resolution_status')) $table->string('supplier_resolution_status')->default('open');
            if (! Schema::hasColumn('purchase_returns', 'approved_by')) $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('purchase_returns', 'approved_at')) $table->timestamp('approved_at')->nullable();
            if (! Schema::hasColumn('purchase_returns', 'dispatched_by')) $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('purchase_returns', 'dispatched_at')) $table->timestamp('dispatched_at')->nullable();
        });
        Schema::table('purchase_return_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_return_items', 'goods_receipt_item_id')) $table->foreignUuid('goods_receipt_item_id')->nullable()->after('purchase_order_item_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('purchase_return_items', 'unit_cost')) $table->decimal('unit_cost', 20, 6)->default(0);
            if (! Schema::hasColumn('purchase_return_items', 'unit')) $table->string('unit', 40)->nullable();
            if (! Schema::hasColumn('purchase_return_items', 'batch_number')) $table->string('batch_number')->nullable();
            if (! Schema::hasColumn('purchase_return_items', 'serial_number')) $table->string('serial_number')->nullable();
            if (! Schema::hasColumn('purchase_return_items', 'reason')) $table->string('reason')->nullable();
            if (! Schema::hasColumn('purchase_return_items', 'dispatched_quantity')) $table->decimal('dispatched_quantity', 20, 6)->default(0);
        });
        Schema::table('supplier_credit_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_credit_notes', 'purchase_return_id')) $table->foreignUuid('purchase_return_id')->nullable()->after('supplier_bill_id')->constrained('purchase_returns')->nullOnDelete();
            if (! Schema::hasColumn('supplier_credit_notes', 'allocated_amount')) $table->decimal('allocated_amount', 20, 6)->default(0);
        });
        if (! Schema::hasTable('purchase_return_shipments')) Schema::create('purchase_return_shipments', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_return_id')->constrained()->cascadeOnDelete(); $table->string('shipment_number');
            $table->string('state')->default('prepared'); $table->date('shipment_date')->nullable(); $table->string('carrier')->nullable();
            $table->string('tracking_reference')->nullable(); $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('dispatched_at')->nullable();
            $table->timestamps(); $table->unique(['tenant_id', 'shipment_number']); $table->unique(['purchase_return_id']);
        });
        if (! Schema::hasTable('purchase_return_shipment_lines')) Schema::create('purchase_return_shipment_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shipment_id')->constrained('purchase_return_shipments')->cascadeOnDelete();
            $table->foreignUuid('purchase_return_item_id')->constrained()->cascadeOnDelete(); $table->decimal('quantity', 20, 6);
            $table->timestamps(); $table->unique(['shipment_id', 'purchase_return_item_id']);
        });
        if (! Schema::hasTable('supplier_debit_notes')) Schema::create('supplier_debit_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete(); $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete(); $table->foreignUuid('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->string('number'); $table->date('document_date'); $table->string('reason'); $table->decimal('tax_adjustment', 20, 6)->default(0); $table->decimal('total', 20, 6); $table->foreignUuid('currency_id')->constrained('accounting_currencies');
            $table->string('state')->default('draft'); $table->text('notes')->nullable(); $table->foreignUuid('created_by')->constrained('users'); $table->timestamps(); $table->unique(['tenant_id', 'number']);
        });
        if (! Schema::hasTable('supplier_refunds')) Schema::create('supplier_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete(); $table->foreignUuid('supplier_credit_note_id')->nullable()->constrained('supplier_credit_notes')->nullOnDelete(); $table->foreignUuid('bank_account_id')->nullable()->constrained()->nullOnDelete(); $table->foreignUuid('currency_id')->constrained('accounting_currencies');
            $table->string('reference'); $table->date('refund_date'); $table->decimal('amount', 20, 6); $table->string('verification_status')->default('unverified'); $table->string('transaction_reference')->nullable(); $table->text('notes')->nullable(); $table->foreignUuid('created_by')->constrained('users'); $table->timestamps(); $table->unique(['tenant_id', 'reference']);
        });

        // Some development databases already contain part of this phase from
        // an earlier interrupted rollout. Keep the migration resumable while
        // preserving the intended lookup indexes on fresh installations.
        if (! Schema::hasIndex('purchase_returns', 'purchase_returns_scope_approval_idx')) {
            Schema::table('purchase_returns', fn (Blueprint $table) => $table->index(['tenant_id', 'company_id', 'branch_id', 'approval_status', 'shipment_status'], 'purchase_returns_scope_approval_idx'));
        }
        if (! Schema::hasIndex('purchase_return_items', 'purchase_return_items_gr_item_idx')) {
            Schema::table('purchase_return_items', fn (Blueprint $table) => $table->index(['tenant_id', 'goods_receipt_item_id'], 'purchase_return_items_gr_item_idx'));
        }
        if (! Schema::hasIndex('supplier_credit_notes', 'supplier_credit_notes_return_idx')) {
            Schema::table('supplier_credit_notes', fn (Blueprint $table) => $table->index(['tenant_id', 'purchase_return_id'], 'supplier_credit_notes_return_idx'));
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('supplier_refunds'); Schema::dropIfExists('supplier_debit_notes'); Schema::dropIfExists('purchase_return_shipment_lines'); Schema::dropIfExists('purchase_return_shipments');
        Schema::table('supplier_credit_notes', fn (Blueprint $t) => $t->dropColumn(['purchase_return_id', 'allocated_amount']));
        Schema::table('purchase_return_items', fn (Blueprint $t) => $t->dropColumn(['goods_receipt_item_id', 'unit_cost', 'unit', 'batch_number', 'serial_number', 'reason', 'dispatched_quantity']));
        Schema::table('purchase_returns', fn (Blueprint $t) => $t->dropColumn(['company_id', 'branch_id', 'goods_receipt_id', 'return_date', 'reason', 'reason_detail', 'priority', 'requested_resolution', 'internal_reference', 'notes', 'approval_status', 'shipment_status', 'supplier_resolution_status', 'approved_by', 'approved_at', 'dispatched_by', 'dispatched_at']));
    }
};
