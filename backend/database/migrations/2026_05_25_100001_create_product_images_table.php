<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->uuid('product_variant_id')->nullable()->index();
            $table->string('path');
            $table->string('disk', 30)->default('public');
            $table->string('mime_type', 50)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('alt_text_en', 255)->nullable();
            $table->string('alt_text_ar', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('product_image_hashes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_image_id')->constrained('product_images')->cascadeOnDelete();
            $table->string('md5_hash', 32)->index();
            $table->string('perceptual_hash', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_hashes');
        Schema::dropIfExists('product_images');
    }
};
