<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_onboarding_applications', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignUuid('assigned_reviewer_id')->nullable()->constrained('users')->nullOnDelete(); $t->foreignUuid('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $t->string('number'); $t->string('application_type')->default('new_supplier_onboarding'); $t->string('status')->default('draft'); $t->string('approval_status')->default('not_submitted'); $t->unsignedInteger('version')->default(1); $t->json('snapshot')->nullable(); $t->text('notes')->nullable(); $t->text('change_request_reason')->nullable(); $t->timestamp('submitted_at')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->unique(['tenant_id','number']); $t->index(['tenant_id','company_id','status']); $t->index(['tenant_id','supplier_id','version']);
        });
        Schema::create('supplier_document_requirements', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete(); $t->string('name'); $t->string('supplier_type')->nullable(); $t->string('country')->nullable(); $t->boolean('is_required')->default(true); $t->boolean('requires_expiry_date')->default(false); $t->boolean('requires_manual_verification')->default(true); $t->unsignedInteger('max_file_size_kb')->default(10240); $t->json('allowed_mime_types')->nullable(); $t->boolean('is_active')->default(true); $t->unsignedInteger('policy_version')->default(1); $t->timestamps(); $t->index(['tenant_id','company_id','is_active']);
        });
        Schema::create('supplier_documents', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('company_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('supplier_id')->constrained()->restrictOnDelete(); $t->foreignUuid('application_id')->nullable()->constrained('supplier_onboarding_applications')->nullOnDelete(); $t->foreignUuid('requirement_id')->nullable()->constrained('supplier_document_requirements')->nullOnDelete(); $t->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete(); $t->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('document_type'); $t->string('original_filename'); $t->string('storage_path'); $t->string('mime_type'); $t->unsignedBigInteger('file_size'); $t->unsignedInteger('version')->default(1); $t->string('status')->default('submitted'); $t->date('issue_date')->nullable(); $t->date('expiry_date')->nullable(); $t->timestamp('verified_at')->nullable(); $t->text('review_notes')->nullable(); $t->timestamps(); $t->index(['tenant_id','company_id','supplier_id','status']); $t->index(['tenant_id','expiry_date','status']);
        });
        Schema::create('supplier_document_reviews', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('supplier_document_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('reviewer_id')->constrained('users')->restrictOnDelete(); $t->string('decision'); $t->text('notes')->nullable(); $t->timestamp('reviewed_at'); $t->timestamps(); $t->index(['tenant_id','supplier_document_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('supplier_document_reviews'); Schema::dropIfExists('supplier_documents'); Schema::dropIfExists('supplier_document_requirements'); Schema::dropIfExists('supplier_onboarding_applications'); }
};
