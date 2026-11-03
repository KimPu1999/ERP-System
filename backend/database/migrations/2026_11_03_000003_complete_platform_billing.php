<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->unsignedInteger('trial_days')->default(14);
            $table->timestamp('archived_at')->nullable();
            $table->index(['is_active', 'archived_at']);
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->decimal('price_snapshot', 20, 6)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->index(['state', 'current_period_ends_at']);
        });

        Schema::create('platform_invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('platform_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 6)->default(1);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('amount', 20, 6);
            $table->string('currency_code', 3);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoice_items');
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['state', 'current_period_ends_at']);
            $table->dropColumn(['price_snapshot', 'currency_code']);
        });
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'archived_at']);
            $table->dropColumn(['trial_days', 'archived_at']);
        });
    }
};
