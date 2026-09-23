<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->boolean('badge_show_delivery_time')->default(false);
            $table->string('badge_delivery_text_en', 100)->nullable();
            $table->string('badge_delivery_text_ar', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn(['badge_show_delivery_time', 'badge_delivery_text_en', 'badge_delivery_text_ar']);
        });
    }
};
