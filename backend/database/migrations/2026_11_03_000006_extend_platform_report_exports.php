<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE report_exports ALTER COLUMN tenant_id DROP NOT NULL');
        Schema::table('report_exports', function (Blueprint $table): void {
            $table->boolean('platform_scope')->default(false);
            $table->index(['platform_scope', 'requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('report_exports', function (Blueprint $table): void {
            $table->dropIndex(['platform_scope', 'requested_by', 'created_at']);
            $table->dropColumn('platform_scope');
        });
        DB::statement('ALTER TABLE report_exports ALTER COLUMN tenant_id SET NOT NULL');
    }
};
