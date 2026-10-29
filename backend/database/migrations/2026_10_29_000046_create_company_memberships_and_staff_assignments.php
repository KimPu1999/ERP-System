<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
        Schema::create('company_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('staff_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('reporting_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'user_id', 'is_primary']);
            $table->index(['tenant_id', 'branch_id', 'department_id']);
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('accepted_at');
            $table->foreignUuid('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignUuid('accepted_by_user_id')->nullable()->after('inviter_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            $table->text('personal_message')->nullable()->after('email');
            $table->index(['company_id', 'email', 'status']);
        });

        DB::table('invitations')->whereNull('accepted_at')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        DB::table('invitations')->whereNotNull('accepted_at')->update(['status' => 'accepted']);

        DB::table('role_assignments')->whereNotNull('company_id')->select(['tenant_id', 'company_id', 'user_id'])->distinct()->orderBy('company_id')->orderBy('user_id')->chunk(500, function ($assignments): void {
            foreach ($assignments as $assignment) {
                DB::table('company_memberships')->updateOrInsert(
                    ['company_id' => $assignment->company_id, 'user_id' => $assignment->user_id],
                    ['id' => (string) \Illuminate\Support\Str::uuid(), 'tenant_id' => $assignment->tenant_id, 'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_login_at');
        });
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['team_id']);
            $table->dropForeign(['accepted_by_user_id']);
            $table->dropColumn(['status', 'branch_id', 'department_id', 'team_id', 'accepted_by_user_id', 'revoked_at', 'personal_message']);
        });
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('company_memberships');
    }
};
