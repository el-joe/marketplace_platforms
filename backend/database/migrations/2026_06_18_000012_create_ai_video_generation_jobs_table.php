<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_video_generation_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('requested_by_type', ['vendor', 'marketer']);
            $table->uuid('requested_by_id');
            $table->uuid('vendor_listing_id')->nullable();
            $table->text('prompt');
            $table->json('source_images')->nullable()->comment('Array of image paths used as input');
            $table->string('result_video_path')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('provider', 50)->nullable()->comment('Which AI service was used');
            $table->integer('credits_consumed')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->onDelete('set null');
            $table->index(['requested_by_type', 'requested_by_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_video_generation_jobs');
    }
};
