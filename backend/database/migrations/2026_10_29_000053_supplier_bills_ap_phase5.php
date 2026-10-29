<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table): void {
            $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignUuid('currency_id')->nullable()->after('branch_id')->constrained('accounting_currencies')->nullOnDelete();
            $table->string('currency_code', 3)->nullable()->after('currency_id');
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_code');
            $table->date('invoice_date')->nullable()->after('supplier_reference');
            $table->date('accounting_date')->nullable()->after('invoice_date');
            $table->date('due_date')->nullable()->after('accounting_date');
            $table->string('payment_terms')->nullable()->after('due_date');
            $table->text('description')->nullable()->after('payment_terms');
            $table->decimal('discount_total', 20, 6)->default(0)->after('subtotal');
            $table->decimal('shipping_total', 20, 6)->default(0)->after('tax_total');
            $table->decimal('grand_total', 20, 6)->default(0)->after('shipping_total');
            $table->string('approval_status')->default('draft')->after('state');
            $table->foreignUuid('accounting_entry_id')->nullable()->after('approval_status')->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->after('accounting_entry_id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignUuid('posted_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('posted_by');
            $table->timestamp('posted_at')->nullable()->after('approved_at');
            $table->string('posting_idempotency_key', 100)->nullable()->after('posted_at');
            $table->index(['company_id', 'state', 'invoice_date']);
            $table->index(['tenant_id', 'supplier_id', 'supplier_reference']);
            $table->unique(['tenant_id', 'posting_idempotency_key']);
        });

        Schema::create('supplier_bill_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignUuid('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
            $table->foreignUuid('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('quantity', 20, 6);
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_price', 20, 6);
            $table->decimal('discount_amount', 20, 6)->default(0);
            $table->decimal('tax_amount', 20, 6)->default(0);
            $table->decimal('line_total', 20, 6)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'purchase_order_item_id']);
        });

        Schema::create('supplier_bill_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_line_id')->constrained('supplier_bill_lines')->cascadeOnDelete();
            $table->string('match_type', 20);
            $table->string('status', 30);
            $table->decimal('eligible_quantity', 20, 6)->default(0);
            $table->decimal('billed_quantity', 20, 6)->default(0);
            $table->text('reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'supplier_bill_id', 'status']);
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('currency_id')->constrained('accounting_currencies')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference');
            $table->string('method', 40);
            $table->string('state', 30)->default('draft');
            $table->date('payment_date');
            $table->decimal('amount', 20, 6);
            $table->decimal('exchange_rate', 24, 12)->default(1);
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('accounting_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('posting_idempotency_key', 100)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
            $table->unique(['tenant_id', 'posting_idempotency_key']);
            $table->index(['company_id', 'state', 'payment_date']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->restrictOnDelete();
            $table->decimal('amount', 20, 6);
            $table->timestamps();
            $table->unique(['supplier_payment_id', 'supplier_bill_id']);
            $table->index(['tenant_id', 'supplier_bill_id']);
        });

        Schema::create('supplier_credit_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->foreignUuid('currency_id')->constrained('accounting_currencies')->restrictOnDelete();
            $table->string('number');
            $table->date('credit_date');
            $table->text('reason');
            $table->string('state', 30)->default('draft');
            $table->decimal('total', 20, 6)->default(0);
            $table->foreignUuid('accounting_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_notes');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bill_matches');
        Schema::dropIfExists('supplier_bill_lines');
        Schema::table('supplier_bills', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'posting_idempotency_key']);
            $table->dropIndex(['company_id', 'state', 'invoice_date']);
            $table->dropIndex(['tenant_id', 'supplier_id', 'supplier_reference']);
            foreach (['accounting_entry_id', 'company_id', 'branch_id', 'currency_id', 'created_by', 'approved_by', 'posted_by'] as $column) $table->dropForeign([$column]);
            $table->dropColumn(['company_id', 'branch_id', 'currency_id', 'currency_code', 'exchange_rate', 'invoice_date', 'accounting_date', 'due_date', 'payment_terms', 'description', 'discount_total', 'shipping_total', 'grand_total', 'approval_status', 'accounting_entry_id', 'created_by', 'approved_by', 'posted_by', 'approved_at', 'posted_at', 'posting_idempotency_key']);
        });
    }
};
