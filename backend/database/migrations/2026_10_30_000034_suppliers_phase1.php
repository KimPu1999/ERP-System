<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('suppliers', 'company_id')) $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('suppliers', 'trading_name')) $table->string('trading_name')->nullable();
            if (! Schema::hasColumn('suppliers', 'supplier_type')) $table->string('supplier_type')->default('business');
            if (! Schema::hasColumn('suppliers', 'category')) $table->string('category')->nullable();
            if (! Schema::hasColumn('suppliers', 'business_registration_number')) $table->string('business_registration_number')->nullable();
            if (! Schema::hasColumn('suppliers', 'website')) $table->string('website')->nullable();
            if (! Schema::hasColumn('suppliers', 'description')) $table->text('description')->nullable();
            if (! Schema::hasColumn('suppliers', 'alternative_phone')) $table->string('alternative_phone')->nullable();
            if (! Schema::hasColumn('suppliers', 'contact_person_name')) $table->string('contact_person_name')->nullable();
            if (! Schema::hasColumn('suppliers', 'contact_person_job_title')) $table->string('contact_person_job_title')->nullable();
            if (! Schema::hasColumn('suppliers', 'contact_person_email')) $table->string('contact_person_email')->nullable();
            if (! Schema::hasColumn('suppliers', 'contact_person_phone')) $table->string('contact_person_phone')->nullable();
            if (! Schema::hasColumn('suppliers', 'preferred_currency_id')) $table->foreignUuid('preferred_currency_id')->nullable()->constrained('accounting_currencies')->nullOnDelete();
            if (! Schema::hasColumn('suppliers', 'payment_terms')) $table->string('payment_terms')->nullable();
            if (! Schema::hasColumn('suppliers', 'preferred_communication_method')) $table->string('preferred_communication_method')->nullable();
            if (! Schema::hasColumn('suppliers', 'internal_notes')) $table->text('internal_notes')->nullable();
            if (! Schema::hasColumn('suppliers', 'archived_at')) $table->timestamp('archived_at')->nullable();
            if (! Schema::hasColumn('suppliers', 'created_by')) $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('suppliers', 'updated_by')) $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        if (! Schema::hasTable('supplier_contacts')) {
            Schema::create('supplier_contacts', function (Blueprint $table): void {
                $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
                $table->string('name'); $table->string('job_title')->nullable(); $table->string('department')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable(); $table->boolean('is_primary')->default(false); $table->text('notes')->nullable(); $table->string('status')->default('active'); $table->timestamps();
                $table->index(['tenant_id', 'supplier_id', 'status']);
            });
        }

        if (! Schema::hasTable('supplier_addresses')) {
            Schema::create('supplier_addresses', function (Blueprint $table): void {
                $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
                $table->string('address_type'); $table->string('recipient')->nullable(); $table->string('country')->nullable(); $table->string('region')->nullable(); $table->string('city')->nullable(); $table->string('township')->nullable(); $table->string('postal_code')->nullable(); $table->text('street_address'); $table->boolean('is_default')->default(false); $table->string('status')->default('active'); $table->timestamps();
                $table->index(['tenant_id', 'supplier_id', 'address_type', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_addresses'); Schema::dropIfExists('supplier_contacts');
    }
};
