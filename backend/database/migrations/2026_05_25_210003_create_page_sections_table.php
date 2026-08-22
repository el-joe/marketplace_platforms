<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id')->index();
            $table->string('name', 150); // "Hero area", "Ramadan zone"
            $table->integer('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('background_color', 20)->nullable(); // hex: #fafafa
            $table->string('background_image_url', 500)->nullable();
            $table->integer('padding_top')->default(0);  // pixels
            $table->integer('padding_bottom')->default(0);
            $table->string('max_width', 20)->nullable(); // full|1200px|960px — NULL = inherit
            $table->timestamps();

            $table->index(['page_id', 'position']);
            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
