<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('block_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique(); // hero_slider|flash_sale|...
            $table->string('label_en', 100);
            $table->string('label_ar', 100);
            $table->string('group', 50); // hero|products|ads_banners|discovery|engagement
            $table->string('icon', 50);  // Tabler icon code: ti-slideshow
            $table->text('description')->nullable();
            $table->json('config_schema')->nullable(); // JSON Schema for admin form generation
            $table->json('default_config')->nullable(); // Pre-filled when dragged onto canvas
            $table->boolean('is_active')->default(true);
            $table->string('requires_permission', 100)->nullable();
            $table->integer('max_per_page')->nullable(); // NULL = unlimited
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_types');
    }
};
