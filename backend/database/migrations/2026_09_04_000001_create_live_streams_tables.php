<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title_en', 200);
            $table->string('title_ar', 200);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->enum('status', ['scheduled', 'live', 'ended'])->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('peak_viewers')->default(0);
            $table->unsignedBigInteger('total_viewers')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->string('stream_key', 64)->unique()->nullable()->comment('Random key used as WebRTC room ID');
            $table->char('created_by_admin_id', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('live_stream_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('live_stream_id', 36)->index();
            $table->char('customer_id', 36)->nullable()->index();
            $table->string('guest_name', 100)->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('live_stream_likes', function (Blueprint $table) {
            $table->id();
            $table->char('live_stream_id', 36)->index();
            $table->char('customer_id', 36)->nullable();
            $table->string('guest_token', 64)->nullable()->comment('Fingerprint for unauthenticated likes');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['live_stream_id', 'customer_id', 'guest_token'], 'live_stream_likes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stream_likes');
        Schema::dropIfExists('live_stream_comments');
        Schema::dropIfExists('live_streams');
    }
};
