<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_listing_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classified_listing_id');
            $table->string('attachment_type', 100);
            $table->string('file_path', 255);
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->uuid('verified_by_admin_id')->nullable();
            $table->timestamps();

            $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->cascadeOnDelete();
            $table->foreign('verified_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_listing_attachments');
    }
};
