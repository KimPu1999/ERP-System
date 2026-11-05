<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('primary_contact_name', 160)->nullable()->after('alternative_phone');
            $table->string('primary_contact_email', 255)->nullable()->after('primary_contact_name');
            $table->string('primary_contact_phone', 60)->nullable()->after('primary_contact_email');
        });

        Schema::table('company_documents', function (Blueprint $table): void {
            $table->date('expires_at')->nullable()->after('size');
            $table->index(['company_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('company_documents', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['primary_contact_name', 'primary_contact_email', 'primary_contact_phone']);
        });
    }
};
