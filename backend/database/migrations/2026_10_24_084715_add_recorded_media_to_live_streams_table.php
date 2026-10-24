<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('source', 12)->default('live')->after('status')->comment('live = studio broadcast, recorded = scheduled audio/video file (radio channel)');
            $table->string('media_path', 255)->nullable()->after('source');
            $table->string('media_type', 10)->nullable()->after('media_path')->comment('audio|video of the uploaded file');
            $table->unsignedInteger('duration_seconds')->nullable()->after('media_type');
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_at')->comment('scheduled_at + duration for recorded channels');
            $table->index(['source', 'status', 'scheduled_at'], 'live_streams_source_status_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropIndex('live_streams_source_status_schedule_idx');
            $table->dropColumn(['source', 'media_path', 'media_type', 'duration_seconds', 'scheduled_end_at']);
        });
    }
};
