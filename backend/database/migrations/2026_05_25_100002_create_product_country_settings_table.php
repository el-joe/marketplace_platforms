<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_country_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->string('unavailable_reason', 500)->nullable();
            $table->string('name_override_en', 255)->nullable();
            $table->string('name_override_ar', 255)->nullable();
            $table->boolean('requires_local_cert')->default(false);
            $table->string('seo_title', 70)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_country_settings');
    }
};
