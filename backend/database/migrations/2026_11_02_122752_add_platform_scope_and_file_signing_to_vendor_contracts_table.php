<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide agreements are signed once per vendor, without a category enrollment. For uploaded
     * documents the exact file signed is copied to a private path so the evidence never changes.
     */
    public function up(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $table) {
            $table->enum('category_scope', ['classified', 'product', 'platform'])->default('classified')->change();
            $table->uuid('vendor_category_enrollment_id')->nullable()->change();
            $table->enum('content_mode', ['editor', 'file'])->default('editor')->after('language_signed');
            $table->enum('acceptance_method', ['registration', 'click_wrap', 'implicit'])->default('click_wrap')->after('status');
            $table->string('signed_file_path')->nullable()->after('pdf_path')->comment('Private-disk copy of the uploaded document the vendor signed.');
            $table->string('signed_file_name')->nullable()->after('signed_file_path');
            $table->string('signed_file_mime', 100)->nullable()->after('signed_file_name');
            $table->timestamp('emailed_at')->nullable()->after('revoked_reason');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $table) {
            $table->dropColumn(['content_mode', 'acceptance_method', 'signed_file_path', 'signed_file_name', 'signed_file_mime', 'emailed_at']);
        });
    }
};
