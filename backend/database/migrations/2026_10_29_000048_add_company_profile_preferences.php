<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('number_format', 30)->default('1,234.56')->after('date_format');
            $table->string('invoice_prefix', 40)->nullable()->after('document_number_prefix');
            $table->string('purchase_order_prefix', 40)->nullable()->after('invoice_prefix');
            $table->string('brand_color', 7)->nullable()->after('purchase_order_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['number_format', 'invoice_prefix', 'purchase_order_prefix', 'brand_color']);
        });
    }
};
