<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['customers', 'leads', 'opportunities'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignUuid('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index(['tenant_id', 'company_id', 'branch_id']);
                $table->index(['tenant_id', 'state']);
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('customer_type')->default('business')->after('code');
            $table->string('legal_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('industry')->nullable();
            $table->string('alternative_phone')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('assigned_salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'customer_type', 'status']);
        });

        Schema::table('customer_contacts', function (Blueprint $table): void {
            $table->string('department')->nullable();
            $table->string('preferred_communication')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('lead_source')->nullable();
            $table->string('priority')->default('normal');
            $table->string('currency')->nullable();
            $table->decimal('estimated_budget', 20, 6)->nullable();
            $table->string('interested_product')->nullable();
            $table->timestamp('expected_contact_at')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->index(['tenant_id', 'company_id', 'state', 'owner_id']);
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('stage')->nullable();
            $table->decimal('probability', 7, 4)->nullable();
            $table->string('currency')->nullable();
            $table->string('priority')->default('normal');
            $table->string('lead_source')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->index(['tenant_id', 'company_id', 'stage', 'owner_id']);
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Primary');
            $table->string('address_type')->default('other');
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('township')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('street_address')->nullable();
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('crm_lead_conversions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lead_id')->unique()->constrained('leads')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('converted_by')->constrained('users');
            $table->string('idempotency_key')->nullable();
            $table->timestamp('converted_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('crm_opportunity_stage_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'opportunity_id', 'created_at']);
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type');
            $table->string('subject');
            $table->timestamp('due_at')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->text('description')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'company_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunity_stage_history');
        Schema::dropIfExists('crm_lead_conversions');
        Schema::dropIfExists('customer_addresses');
        foreach (['opportunities', 'leads', 'customer_contacts', 'customers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['company_id', 'branch_id']);
            });
        }
    }
};
