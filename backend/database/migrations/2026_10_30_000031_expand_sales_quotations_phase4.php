<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table): void {
            $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignUuid('currency_id')->nullable()->after('customer_id')->constrained('accounting_currencies')->nullOnDelete();
            $table->foreignUuid('assigned_salesperson_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            // The price-list table is created below in this additive migration; keep this reference
            // nullable here and enforce tenant/company ownership in the quotation service.
            $table->uuid('price_list_id')->nullable()->after('currency_id');
            $table->foreignUuid('current_version_id')->nullable()->after('price_list_id');
            $table->date('quotation_date')->nullable();
            $table->string('customer_reference')->nullable();
            $table->string('approval_status')->default('not_required');
            $table->string('response_status')->default('pending');
            $table->string('conversion_status')->default('not_converted');
            $table->unsignedInteger('version_number')->default(1);
            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->decimal('additional_charges', 20, 6)->default(0);
            $table->date('accepted_at')->nullable();
            $table->foreignUuid('converted_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->index(['tenant_id', 'company_id', 'branch_id', 'state']);
            $table->index(['tenant_id', 'approval_status', 'response_status']);
        });
        Schema::table('sales_quotation_items', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->string('discount_type')->default('percentage');
            $table->decimal('discount_amount', 20, 6)->default(0);
            $table->string('tax_code')->nullable();
        });

        Schema::create('sales_price_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('list_type')->default('standard'); $table->foreignUuid('currency_id')->nullable()->constrained('accounting_currencies')->nullOnDelete(); $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete(); $table->date('valid_from')->nullable(); $table->date('valid_until')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps(); $table->index(['tenant_id','company_id','is_active']);
        });
        Schema::create('sales_price_list_items', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('price_list_id')->constrained('sales_price_lists')->cascadeOnDelete(); $table->foreignUuid('variant_id')->constrained('product_variants'); $table->decimal('minimum_quantity',20,6)->default(1); $table->decimal('unit_price',20,6); $table->date('valid_from')->nullable(); $table->date('valid_until')->nullable(); $table->timestamps(); $table->unique(['price_list_id','variant_id','minimum_quantity']);
        });
        Schema::create('sales_quotation_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('quotation_id')->constrained('sales_quotations')->cascadeOnDelete(); $table->unsignedInteger('version_number'); $table->foreignUuid('created_by')->constrained('users'); $table->string('revision_reason')->nullable(); $table->json('snapshot'); $table->timestamps(); $table->unique(['quotation_id','version_number']);
        });
        Schema::create('sales_quotation_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('quotation_id')->constrained('sales_quotations')->cascadeOnDelete(); $table->foreignUuid('approver_id')->constrained('users'); $table->string('state')->default('pending'); $table->text('remarks')->nullable(); $table->timestamp('decided_at')->nullable(); $table->timestamps(); $table->index(['tenant_id','quotation_id','state']);
        });
        Schema::create('sales_quotation_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('quotation_id')->constrained('sales_quotations')->cascadeOnDelete(); $table->foreignUuid('version_id')->nullable()->constrained('sales_quotation_versions')->nullOnDelete(); $table->string('response')->nullable(); $table->string('source')->default('internal'); $table->text('evidence')->nullable(); $table->timestamp('responded_at'); $table->foreignUuid('responded_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['tenant_id','quotation_id']);
        });
        Schema::create('sales_quotation_communications', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('quotation_id')->constrained('sales_quotations')->cascadeOnDelete(); $table->foreignUuid('sent_by')->constrained('users'); $table->string('recipient'); $table->string('subject'); $table->string('state')->default('queued'); $table->timestamp('queued_at')->nullable(); $table->timestamp('attempted_at')->nullable(); $table->timestamp('confirmed_at')->nullable(); $table->text('failure_reason')->nullable(); $table->timestamps(); $table->index(['tenant_id','quotation_id','state']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_communications'); Schema::dropIfExists('sales_quotation_responses'); Schema::dropIfExists('sales_quotation_approvals'); Schema::dropIfExists('sales_quotation_versions'); Schema::dropIfExists('sales_price_list_items'); Schema::dropIfExists('sales_price_lists');
        Schema::table('sales_quotation_items', function (Blueprint $table): void { $table->dropColumn(['description','unit','discount_type','discount_amount','tax_code']); });
        Schema::table('sales_quotations', function (Blueprint $table): void { $table->dropColumn(['company_id','branch_id','currency_id','assigned_salesperson_id','price_list_id','current_version_id','quotation_date','customer_reference','approval_status','response_status','conversion_status','version_number','payment_terms','delivery_terms','terms_and_conditions','customer_notes','internal_notes','additional_charges','accepted_at','converted_order_id']); });
    }
};
