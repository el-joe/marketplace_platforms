<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_listings', function (Blueprint $table) {
            $table->dropColumn([
                'aplus_images',
                'aplus_video_url',
                'aplus_infographic_urls',
                'aplus_headline_en',
                'aplus_headline_ar',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_listings', function (Blueprint $table) {
            $table->json('aplus_images')->nullable()->after('daily_deal_ends_at');
            $table->string('aplus_video_url', 500)->nullable()->after('aplus_images');
            $table->json('aplus_infographic_urls')->nullable()->after('aplus_video_url');
            $table->text('aplus_headline_en')->nullable()->after('aplus_infographic_urls');
            $table->text('aplus_headline_ar')->nullable()->after('aplus_headline_en');
        });
    }
};
