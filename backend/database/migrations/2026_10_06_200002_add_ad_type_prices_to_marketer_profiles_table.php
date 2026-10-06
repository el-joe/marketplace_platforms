<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->bigInteger('story_price')->nullable()->after('ad_price');
            $table->bigInteger('post_price')->nullable()->after('story_price');
            $table->bigInteger('video_price')->nullable()->after('post_price');
        });
    }

    public function down(): void
    {
        Schema::table('marketer_profiles', function (Blueprint $table) {
            $table->dropColumn(['story_price', 'post_price', 'video_price']);
        });
    }
};
