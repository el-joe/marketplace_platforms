<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add file FK columns to page builder media tables.
     * Uses the platform's own `files` table instead of Spatie Media Library.
     */
    public function up(): void
    {
        Schema::table('slider_slides', function (Blueprint $table) {
            $table->unsignedBigInteger('desktop_file_id')->nullable()->after('position');
            $table->unsignedBigInteger('mobile_file_id')->nullable()->after('desktop_file_id');

            $table->foreign('desktop_file_id')
                ->references('id')->on('files')->onDelete('set null');
            $table->foreign('mobile_file_id')
                ->references('id')->on('files')->onDelete('set null');
        });

        Schema::table('ad_image_items', function (Blueprint $table) {
            $table->unsignedBigInteger('file_id')->nullable()->after('position');

            $table->foreign('file_id')
                ->references('id')->on('files')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('slider_slides', function (Blueprint $table) {
            $table->dropForeign(['desktop_file_id']);
            $table->dropForeign(['mobile_file_id']);
            $table->dropColumn(['desktop_file_id', 'mobile_file_id']);
        });

        Schema::table('ad_image_items', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->dropColumn('file_id');
        });
    }
};
