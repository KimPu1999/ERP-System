<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('approval_required')->default(true);
            $table->boolean('invoice_on_order')->default(false);
            $table->boolean('invoice_on_delivery')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sales_settings'); }
};
