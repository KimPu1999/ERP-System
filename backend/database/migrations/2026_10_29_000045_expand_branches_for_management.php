<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('branch_type', 80)->nullable();
            $table->date('opening_date')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('alternative_phone', 60)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('township', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('street_address')->nullable();
            $table->foreignUuid('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('business_hours')->nullable();
            $table->string('timezone', 80)->nullable();
            $table->string('document_number_prefix', 40)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampTz('archived_at')->nullable();
            $table->index(['tenant_id', 'company_id', 'status']);
            $table->index(['company_id', 'manager_user_id']);
        });

        DB::table('branches')->where('is_active', false)->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'company_id', 'status']);
            $table->dropIndex(['company_id', 'manager_user_id']);
            $table->dropForeign(['manager_user_id']);
            $table->dropColumn([
                'branch_type', 'opening_date', 'description', 'email', 'phone', 'alternative_phone',
                'country', 'region', 'city', 'township', 'postal_code', 'street_address', 'manager_user_id',
                'business_hours', 'timezone', 'document_number_prefix', 'status', 'archived_at',
            ]);
        });
    }
};
