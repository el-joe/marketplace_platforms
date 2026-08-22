<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Each slide in a hero_slider block gets its own row (enables media library + clean reorder)
    public function up(): void
    {
        Schema::create('slider_slides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index(); // must be a hero_slider block
            $table->integer('position')->default(0);
            // media_id and mobile_media_id intentionally omitted until Spatie Media Library is installed
            $table->string('title_en', 255)->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('subtitle_en', 500)->nullable();
            $table->string('subtitle_ar', 500)->nullable();
            $table->string('cta_label_en', 100)->nullable();
            $table->string('cta_label_ar', 100)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->boolean('cta_open_new_tab')->default(false);
            $table->char('text_color', 7)->default('#ffffff');
            $table->string('text_position', 10)->default('left'); // left|center|right
            $table->decimal('overlay_opacity', 3, 2)->default(0.30); // 0.00–1.00
            $table->string('link_type', 20)->nullable(); // product|category|flash_sale|url
            $table->uuid('link_reference_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_until')->nullable();
            $table->timestamps();

            $table->index(['page_block_id', 'position', 'is_active']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slider_slides');
    }
};
