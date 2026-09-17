<?php

use App\Models\BannerPlacementDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE banners
            MODIFY COLUMN placement_code ENUM(
                'homepage_hero',
                'homepage_secondary_left',
                'homepage_secondary_right',
                'homepage_midpage',
                'category_top_{slug}',
                'category_sidebar',
                'search_top',
                'cart_banner',
                'checkout_banner',
                'product_page_bottom',
                'app_splash',
                'app_home_top',
                'email_header',
                'product_page_inline_1',
                'product_page_inline_2',
                'gift_cards_hero',
                'gift_cards_redeem'
            ) NOT NULL
        ");

        BannerPlacementDefinition::insert([
            [
                'id'                  => (string) \Illuminate\Support\Str::uuid(),
                'code'                => 'gift_cards_hero',
                'name'                => 'Gift Cards – Hero Banner',
                'description'         => 'Top hero banner on the gift cards landing page.',
                'width_px'            => 1400,
                'height_px'           => 298,
                'mobile_width_px'     => 750,
                'mobile_height_px'    => 300,
                'max_file_size_kb'    => 512,
                'allowed_formats'     => json_encode(['jpg', 'jpeg', 'png', 'webp', 'avif']),
                'device_restriction'  => 'all',
                'max_simultaneous'    => 1,
                'supports_vendor_ads' => 0,
                'is_active'           => 1,
                'sort_order'          => 160,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'id'                  => (string) \Illuminate\Support\Str::uuid(),
                'code'                => 'gift_cards_redeem',
                'name'                => 'Gift Cards – Redeem Banner',
                'description'         => 'Secondary "how to redeem" banner on the gift cards landing page.',
                'width_px'            => 1400,
                'height_px'           => 253,
                'mobile_width_px'     => 750,
                'mobile_height_px'    => 260,
                'max_file_size_kb'    => 512,
                'allowed_formats'     => json_encode(['jpg', 'jpeg', 'png', 'webp', 'avif']),
                'device_restriction'  => 'all',
                'max_simultaneous'    => 1,
                'supports_vendor_ads' => 0,
                'is_active'           => 1,
                'sort_order'          => 170,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
        ]);
    }

    public function down(): void
    {
        BannerPlacementDefinition::whereIn('code', ['gift_cards_hero', 'gift_cards_redeem'])->delete();

        DB::statement("
            ALTER TABLE banners
            MODIFY COLUMN placement_code ENUM(
                'homepage_hero',
                'homepage_secondary_left',
                'homepage_secondary_right',
                'homepage_midpage',
                'category_top_{slug}',
                'category_sidebar',
                'search_top',
                'cart_banner',
                'checkout_banner',
                'product_page_bottom',
                'app_splash',
                'app_home_top',
                'email_header',
                'product_page_inline_1',
                'product_page_inline_2'
            ) NOT NULL
        ");
    }
};
