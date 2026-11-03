<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->index(['tenant_id', 'status', 'created_at'], 'tenant_memberships_tenant_status_created_idx');
        });

        Schema::table('role_assignments', function (Blueprint $table): void {
            $table->index(['tenant_id', 'user_id', 'company_id'], 'role_assignments_tenant_user_company_idx');
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->index(['tenant_id', 'company_id', 'status', 'expires_at'], 'invitations_tenant_company_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropIndex('invitations_tenant_company_status_expires_idx');
        });
        Schema::table('role_assignments', function (Blueprint $table): void {
            $table->dropIndex('role_assignments_tenant_user_company_idx');
        });
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropIndex('tenant_memberships_tenant_status_created_idx');
        });
    }
};
