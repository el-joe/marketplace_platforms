<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->enum('type', ['audio', 'video']);
            $table->string('stream_url', 500)->nullable();
            $table->string('fallback_media_path', 255)->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_channels');
    }
};
