<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable();
            $table->string('path');
            $table->string('storage_type')->default('public');
            $table->string('file_type')->default('image');
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->morphs('model');
            $table->string('alt_text_ar')->nullable();
            $table->string('alt_text_en')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('files_hashes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('file_id')->index();
            $table->char('md5_hash', 32);
            $table->char('perceptual_hash', 16)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files_hashes');
        Schema::dropIfExists('files');
    }
};
