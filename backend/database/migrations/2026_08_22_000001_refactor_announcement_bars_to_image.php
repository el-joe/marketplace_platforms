<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcement_bars', function (Blueprint $table) {
            $table->string('image_url', 1000)->nullable()->after('name');

            $table->dropColumn([
                'message_en',
                'message_ar',
                'cta_label_en',
                'cta_label_ar',
                'bg_color_hex',
                'text_color_hex',
                'is_dismissible',
            ]);
        });

        Schema::dropIfExists('customer_dismissed_announcement_bars');
    }

    public function down(): void
    {
        Schema::table('announcement_bars', function (Blueprint $table) {
            $table->dropColumn('image_url');
            $table->string('message_en', 500)->default('');
            $table->string('message_ar', 500)->default('');
            $table->string('cta_label_en', 100)->nullable();
            $table->string('cta_label_ar', 100)->nullable();
            $table->string('bg_color_hex', 7)->nullable();
            $table->string('text_color_hex', 7)->nullable();
            $table->boolean('is_dismissible')->default(true);
        });
    }
};
