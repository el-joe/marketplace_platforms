<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_product_certifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('original_filename', 255)->nullable()
                ->comment('Original uploaded filename, stored for display');
            $table->string('cert_name', 255)->nullable()
                ->comment('Optional: vendor-provided label e.g. "SFDA Certificate #12345"');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])
                ->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable()
                ->comment('Admin sets expiry date on approval if cert has a validity period');
            $table->foreignUuid('reviewed_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // A vendor can only have one active cert per product per country
            $table->unique(['vendor_id', 'product_id', 'country_id'], 'vpc_vendor_product_country_unique');

            $table->index('vendor_id');
            $table->index('product_id');
            $table->index('country_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_product_certifications');
    }
};
