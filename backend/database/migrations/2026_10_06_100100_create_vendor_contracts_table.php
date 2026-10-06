<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per signature. Rendered text, variables and hash are frozen at signing time and never updated.
     */
    public function up(): void
    {
        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('vendor_category_enrollment_id');
            $table->uuid('classified_category_id')->nullable();
            $table->enum('category_scope', ['classified', 'product'])->default('classified');
            $table->uuid('product_category_id')->nullable();
            $table->uuid('contract_template_id')->nullable()->comment('Exact template row used for this signature.');
            $table->unsignedInteger('template_version')->default(1);
            $table->enum('language_signed', ['en', 'ar'])->default('en');
            $table->longText('rendered_content')->comment('Frozen contract text in the signed language. Never updated after signing.');
            $table->char('rendered_content_hash', 64)->comment('sha256 of rendered_content + variables JSON.');
            $table->json('variables')->comment('Frozen resolved variable values used when rendering.');
            $table->string('signature_path')->nullable()->comment('Private-disk path of the signature PNG.');
            $table->uuid('signed_by_vendor_admin_id')->nullable();
            $table->string('signer_name', 150);
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->enum('status', ['active', 'superseded', 'revoked'])->default('active');
            $table->string('pdf_path')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->timestamps();

            $table->foreign('vendor_category_enrollment_id', 'vc_enrollment_foreign')->references('id')->on('vendor_category_enrollments')->restrictOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->restrictOnDelete();
            $table->foreign('product_category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('contract_template_id')->references('id')->on('classified_contract_templates')->nullOnDelete();
            $table->foreign('signed_by_vendor_admin_id')->references('id')->on('vendor_admins')->nullOnDelete();

            $table->index(['vendor_id', 'classified_category_id', 'status'], 'vc_vendor_category_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_contracts');
    }
};
