<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->enum('document_type', ['business_license', 'tax_certificate', 'owner_id', 'bank_proof', 'vat_registration']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->uuid('verified_by_admin_id')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
