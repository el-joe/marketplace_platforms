<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_tryon_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->nullable();
            $table->uuid('vendor_listing_id');
            $table->string('customer_photo_path');
            $table->string('result_image_path')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('provider', 50)->nullable()->comment('Which AI service was used');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->onDelete('cascade');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_tryon_sessions');
    }
};
