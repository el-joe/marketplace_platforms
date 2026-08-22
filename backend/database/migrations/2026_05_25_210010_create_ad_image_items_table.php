<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Individual images in ad_images_2col / 3col / 4col / split_banner blocks
    public function up(): void
    {
        Schema::create('ad_image_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->integer('position')->default(0); // left-to-right order
            // media_id and mobile_media_id intentionally omitted until Spatie Media Library is installed
            $table->string('title_en', 255)->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->boolean('link_open_new_tab')->default(false);
            $table->string('alt_text_en', 255)->nullable();
            $table->string('alt_text_ar', 255)->nullable();
            $table->boolean('show_title_overlay')->default(true);
            $table->string('aspect_ratio', 10)->default('4:3'); // 1:1|4:3|16:9|2:1|3:4
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['page_block_id', 'position', 'is_active']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_image_items');
    }
};
