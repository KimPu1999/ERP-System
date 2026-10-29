<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('legal_name', 200)->nullable()->after('name');
            $table->string('trading_name', 200)->nullable()->after('legal_name');
            $table->string('business_type', 120)->nullable()->after('trading_name');
            $table->string('registration_number', 120)->nullable()->after('business_type');
            $table->string('tax_identifier', 120)->nullable()->after('registration_number');
            $table->text('tax_registration_details')->nullable()->after('tax_identifier');
            $table->date('registration_date')->nullable()->after('tax_registration_details');
            $table->text('description')->nullable()->after('registration_date');
            $table->string('email', 255)->nullable()->after('description');
            $table->string('phone', 60)->nullable()->after('email');
            $table->string('website', 255)->nullable()->after('phone');
            $table->string('country_code', 2)->nullable()->after('website');
            $table->string('region', 120)->nullable()->after('country_code');
            $table->string('city', 120)->nullable()->after('region');
            $table->string('postal_code', 30)->nullable()->after('city');
            $table->string('street_address', 255)->nullable()->after('postal_code');
            $table->string('logo_path')->nullable()->after('street_address');
            $table->string('cover_image_path')->nullable()->after('logo_path');
            $table->string('base_currency', 3)->nullable()->after('cover_image_path');
            $table->string('default_locale', 10)->default('en')->after('base_currency');
            $table->string('date_format', 30)->default('YYYY-MM-DD')->after('default_locale');
        });

        Schema::create('company_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('category', 40);
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['tenant_id', 'company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name', 'trading_name', 'business_type', 'registration_number',
                'tax_identifier', 'tax_registration_details', 'registration_date', 'description',
                'email', 'phone', 'website', 'country_code', 'region', 'city', 'postal_code',
                'street_address', 'logo_path', 'cover_image_path', 'base_currency',
                'default_locale', 'date_format',
            ]);
        });
    }
};
