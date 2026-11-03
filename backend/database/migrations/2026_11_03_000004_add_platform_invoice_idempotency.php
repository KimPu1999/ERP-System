<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table): void {
            $table->string('billing_period_key')->nullable();
            $table->unique(['subscription_id', 'billing_period_key']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table): void {
            $table->dropUnique(['subscription_id', 'billing_period_key']);
            $table->dropColumn('billing_period_key');
        });
    }
};
