<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope', 30)->default('platform');
            $table->string('setting_key', 80);
            $table->jsonb('value');
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scope', 'setting_key']);
            $table->index(['scope', 'updated_at']);
        });

        Schema::create('platform_branding_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('asset_key', 40)->unique();
            $table->string('path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key', 80);
            $table->string('locale', 10)->default('en');
            $table->string('subject', 255);
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['template_key', 'locale']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('platform_email_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('platform_email_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject', 255);
            $table->text('body');
            $table->string('status', 20);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['template_id', 'version']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['platform_settings', 'platform_branding_assets', 'platform_email_templates', 'platform_email_template_versions'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY {$table}_platform_only ON {$table} USING (current_setting('app.platform_admin', true) = 'true') WITH CHECK (current_setting('app.platform_admin', true) = 'true')");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['platform_email_template_versions', 'platform_email_templates', 'platform_branding_assets', 'platform_settings'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_platform_only ON {$table}");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }
        Schema::dropIfExists('platform_email_template_versions');
        Schema::dropIfExists('platform_email_templates');
        Schema::dropIfExists('platform_branding_assets');
        Schema::dropIfExists('platform_settings');
    }
};
