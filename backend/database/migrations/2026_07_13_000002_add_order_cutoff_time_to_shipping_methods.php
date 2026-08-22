<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->time('order_cutoff_time')->nullable()->after('max_delivery_days')
                ->comment('Daily cutoff for next-slot-delivery eligibility. NULL = no cutoff applies for this method.');
            $table->unsignedTinyInteger('handling_time_hours')->default(24)->after('order_cutoff_time')
                ->comment('Vendor handling time added to delivery estimate before shipping transit begins');
        });

        DB::table('shipping_methods')->where('code', 'express')->update([
            'badge_label_en'       => 'express',
            'badge_label_ar'       => 'إكسبريس',
            'badge_color_hex'      => '#FF6B00',
            'badge_text_color_hex' => '#FFFFFF',
            'delivery_label_en'    => 'Get it Tomorrow',
            'delivery_label_ar'    => 'استلامه غداً',
            'is_express_type'      => true,
            'order_cutoff_time'    => '12:00:00',
            'handling_time_hours'  => 2,
        ]);

        DB::table('shipping_methods')->where('code', 'same_day')->update([
            'badge_label_en'       => 'same day',
            'badge_label_ar'       => 'توصيل اليوم',
            'badge_color_hex'      => '#7C3AED',
            'badge_text_color_hex' => '#FFFFFF',
            'is_express_type'      => true,
            'order_cutoff_time'    => '10:00:00',
            'handling_time_hours'  => 1,
        ]);

        DB::table('shipping_methods')->where('code', 'standard')->update([
            'badge_label_en'    => 'supermall',
            'badge_label_ar'    => 'سوبرمول',
            'badge_color_hex'   => '#1B2B6B',
            'badge_text_color_hex' => '#FFFFFF',
            'delivery_label_en' => 'Scheduled Delivery',
            'is_express_type'   => false,
        ]);

        DB::table('shipping_methods')->where('code', 'scheduled')->update([
            'badge_label_en'  => null,
            'badge_label_ar'  => null,
            'is_express_type' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn(['order_cutoff_time', 'handling_time_hours']);
        });
    }
};
