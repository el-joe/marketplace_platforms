<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('playback_mode', 10)->default('synced')->after('media_type')
                ->comment('synced = true live (same moment for everyone, no seek), free = on-demand with full controls');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn('playback_mode');
        });
    }
};
