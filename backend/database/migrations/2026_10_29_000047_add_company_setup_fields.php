<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('industry', 120)->nullable()->after('business_type');
            $table->string('alternative_phone', 60)->nullable()->after('phone');
            $table->string('township', 120)->nullable()->after('city');
            $table->date('fiscal_year_start')->nullable()->after('date_format');
            $table->string('document_number_prefix', 40)->nullable()->after('fiscal_year_start');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'industry',
                'alternative_phone',
                'township',
                'fiscal_year_start',
                'document_number_prefix',
            ]);
        });
    }
};
