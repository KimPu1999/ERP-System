<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_log_archives', function (Blueprint $table): void {
            $table->uuid('audit_log_id')->primary();
            $table->string('archive_hash', 64)->unique();
            $table->jsonb('payload');
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index('archived_at');
        });
    }

    public function down(): void { Schema::dropIfExists('audit_log_archives'); }
};
