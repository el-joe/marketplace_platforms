<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_image_items', function (Blueprint $table): void {
            $table->string('subtitle_en', 255)->nullable()->after('title_ar');
            $table->string('subtitle_ar', 255)->nullable()->after('subtitle_en');
            $table->string('badge_label_en', 100)->nullable()->after('subtitle_ar');
            $table->string('badge_label_ar', 100)->nullable()->after('badge_label_en');
        });
    }

    public function down(): void
    {
        Schema::table('ad_image_items', function (Blueprint $table): void {
            $table->dropColumn(['subtitle_en', 'subtitle_ar', 'badge_label_en', 'badge_label_ar']);
        });
    }
};
