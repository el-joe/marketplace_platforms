<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            $table->string('name_en', 150)->nullable()->after('name');
            $table->string('name_ar', 150)->nullable()->after('name_en');
            $table->string('background_image_url_en', 500)->nullable()->after('background_image_url');
            $table->string('background_image_url_ar', 500)->nullable()->after('background_image_url_en');
        });

        DB::statement('UPDATE page_sections SET name_en = name, name_ar = name');
        DB::statement('UPDATE page_sections SET background_image_url_en = background_image_url, background_image_url_ar = background_image_url WHERE background_image_url IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_ar', 'background_image_url_en', 'background_image_url_ar']);
        });
    }
};
