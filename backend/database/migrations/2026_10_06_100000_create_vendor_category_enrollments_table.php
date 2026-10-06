<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A vendor's relationship to one classified or product category that requires a contract.
     */
    public function up(): void
    {
        Schema::create('vendor_category_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('classified_category_id')->nullable();
            $table->enum('category_scope', ['classified', 'product'])->default('classified');
            $table->uuid('product_category_id')->nullable();
            $table->enum('status', ['pending_signature', 'signed', 're_sign_required', 'revoked'])->default('pending_signature');
            $table->uuid('active_contract_id')->nullable()->comment('Pointer to the currently signed vendor_contracts row.');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by_admin_id')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->restrictOnDelete();
            $table->foreign('product_category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('revoked_by_admin_id')->references('id')->on('admins')->nullOnDelete();

            $table->unique(['vendor_id', 'classified_category_id'], 'vce_vendor_category_unique');
            $table->unique(['vendor_id', 'product_category_id'], 'vce_vendor_product_unique');
            $table->index(['classified_category_id', 'status'], 'vce_category_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_category_enrollments');
    }
};
