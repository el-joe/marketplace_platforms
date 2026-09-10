<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner_placement_definitions', function (Blueprint $t) {
            $t->unsignedInteger('mobile_width_px')->nullable()->after('height_px');
            $t->unsignedInteger('mobile_height_px')->nullable()->after('mobile_width_px');
        });

        // Only placements that AS-04/AS-08 actually render may be sold.
        DB::table('banner_placement_definitions')->update(['supports_vendor_ads' => 0]);
        DB::table('banner_placement_definitions')
            ->whereIn('code', ['cart_banner', 'product_page_bottom', 'search_top', 'category_top'])
            ->update(['supports_vendor_ads' => 1]);
        DB::table('banner_placement_definitions')->where('code', 'cart_banner')
            ->update(['mobile_width_px' => 375, 'mobile_height_px' => 100]);
        DB::table('banner_placement_definitions')
            ->whereIn('code', ['product_page_bottom', 'search_top', 'category_top'])
            ->update(['mobile_width_px' => 375, 'mobile_height_px' => 120]);
    }

    public function down(): void
    {
        Schema::table('banner_placement_definitions', function (Blueprint $t) {
            $t->dropColumn(['mobile_width_px', 'mobile_height_px']);
        });
    }
};
