<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['sales_quotations', 'sales_orders', 'sales_invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                $blueprint->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
                $blueprint->foreignUuid('currency_id')->nullable()->after('branch_id')->constrained('accounting_currencies')->nullOnDelete();
                $blueprint->decimal('exchange_rate', 24, 12)->default(1)->after('currency_id');
                $blueprint->decimal('base_total', 24, 6)->nullable()->after('total');
                $blueprint->index(['tenant_id', 'company_id', 'branch_id']);
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignUuid('currency_id')->nullable()->after('branch_id')->constrained('accounting_currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 24, 12)->default(1)->after('currency_id');
            $table->decimal('base_total', 24, 6)->nullable()->after('notes');
            $table->index(['tenant_id', 'company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        foreach (['sales_quotations', 'sales_orders', 'sales_invoices', 'purchase_orders'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['company_id']);
                $blueprint->dropForeign(['branch_id']);
                $blueprint->dropForeign(['currency_id']);
                $blueprint->dropIndex([$table === 'purchase_orders' ? 'tenant_id' : 'tenant_id', 'company_id', 'branch_id']);
                $blueprint->dropColumn(['company_id', 'branch_id', 'currency_id', 'exchange_rate', 'base_total']);
            });
        }
    }
};
