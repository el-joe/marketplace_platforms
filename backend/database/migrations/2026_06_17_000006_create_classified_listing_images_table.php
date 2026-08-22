<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_listing_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classified_listing_id');
            $table->string('file_path', 255);
            $table->integer('position')->default(0);
            $table->tinyInteger('is_primary')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_listing_images');
    }
};
