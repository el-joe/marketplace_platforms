<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_image_enhancement_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_image_id');
            $table->string('original_path');
            $table->string('enhanced_path')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('provider', 50)->nullable()->comment('Which AI service was used');
            $table->text('error_message')->nullable();
            $table->enum('requested_by_type', ['vendor', 'admin']);
            $table->uuid('requested_by_id');
            $table->tinyInteger('applied')->default(0)->comment('Did vendor accept and apply the enhanced version');
            $table->timestamps();

            $table->foreign('product_image_id')->references('id')->on('product_images')->onDelete('cascade');
            $table->index(['requested_by_type', 'requested_by_id'], 'ai_jobs_requested_by_index');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_image_enhancement_jobs');
    }
};
