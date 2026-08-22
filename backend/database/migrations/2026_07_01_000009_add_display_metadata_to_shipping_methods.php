<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->string('badge_label_en', 50)->nullable()->after('code')
                ->comment('Short label shown on listing card badge, e.g. "express", "supermall"');
            $table->string('badge_label_ar', 50)->nullable()->after('badge_label_en');
            $table->string('badge_color_hex', 7)->default('#1a1a2e')->after('badge_label_ar')
                ->comment('Background color for the badge pill on listing cards');
            $table->string('badge_text_color_hex', 7)->default('#FFFFFF')->after('badge_color_hex');
            $table->string('delivery_label_en', 100)->nullable()->after('badge_text_color_hex')
                ->comment('Full label shown in the Delivery Information panel on product detail');
            $table->string('delivery_label_ar', 100)->nullable()->after('delivery_label_en');
            $table->boolean('is_express_type')->default(false)->after('delivery_label_ar')
                ->comment('True for same_day and express — drives speed-highlight badge treatment on listing cards');
            $table->boolean('show_estimated_price')->default(true)->after('is_express_type')
                ->comment('Whether to show the calculated shipping price in the delivery info panel');
            $table->unsignedInteger('display_priority')->default(0)->after('show_estimated_price')
                ->comment('Order methods appear in the Delivery Information panel — lower = shown first');
        });

        DB::table('shipping_methods')->where('code', 'same_day')->update([
            'badge_label_en'       => 'supermall',
            'badge_label_ar'       => 'سوبرمول',
            'badge_color_hex'      => '#7C3AED',
            'badge_text_color_hex' => '#FFFFFF',
            'delivery_label_en'    => 'Get in [REALTIME]',
            'delivery_label_ar'    => 'احصل عليه [REALTIME]',
            'is_express_type'      => true,
            'show_estimated_price' => true,
            'display_priority'     => 1,
        ]);

        DB::table('shipping_methods')->where('code', 'express')->update([
            'badge_label_en'       => 'express',
            'badge_label_ar'       => 'اكسبريس',
            'badge_color_hex'      => '#1a1a2e',
            'badge_text_color_hex' => '#FACC15',
            'delivery_label_en'    => 'Get it Tomorrow',
            'delivery_label_ar'    => 'استلمه غداً',
            'is_express_type'      => true,
            'show_estimated_price' => true,
            'display_priority'     => 2,
        ]);

        DB::table('shipping_methods')->where('code', 'scheduled')->update([
            'badge_label_en'       => '',
            'badge_label_ar'       => '',
            'badge_color_hex'      => '#6B7280',
            'badge_text_color_hex' => '#FFFFFF',
            'delivery_label_en'    => 'Standard Delivery',
            'delivery_label_ar'    => 'التوصيل العادي',
            'is_express_type'      => false,
            'show_estimated_price' => true,
            'display_priority'     => 3,
        ]);

        DB::table('shipping_methods')->where('code', 'pickup')->update([
            'badge_label_en'       => 'pickup',
            'badge_label_ar'       => 'استلام',
            'badge_color_hex'      => '#059669',
            'badge_text_color_hex' => '#FFFFFF',
            'delivery_label_en'    => 'Click & Collect',
            'delivery_label_ar'    => 'استلام من المتجر',
            'is_express_type'      => false,
            'show_estimated_price' => false,
            'display_priority'     => 4,
        ]);
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn([
                'badge_label_en',
                'badge_label_ar',
                'badge_color_hex',
                'badge_text_color_hex',
                'delivery_label_en',
                'delivery_label_ar',
                'is_express_type',
                'show_estimated_price',
                'display_priority',
            ]);
        });
    }
};
