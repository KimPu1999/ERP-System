<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignUuid('parent_department_id')->nullable()->after('branch_id')->constrained('departments')->nullOnDelete();
            $table->foreignUuid('manager_user_id')->nullable()->after('parent_department_id')->constrained('users')->nullOnDelete();
            $table->text('description')->nullable()->after('code');
            $table->string('status')->default('active')->after('description');
            $table->index(['tenant_id', 'company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropForeign(['parent_department_id']);
            $table->dropForeign(['manager_user_id']);
            $table->dropColumn(['parent_department_id', 'manager_user_id', 'description', 'status']);
        });
    }
};
