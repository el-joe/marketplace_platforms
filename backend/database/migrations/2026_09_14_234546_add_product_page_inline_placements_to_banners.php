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
                'product_page_inline_2'
            ) NOT NULL
        ");

        BannerPlacementDefinition::insert([
            [
                'id'                  => (string) \Illuminate\Support\Str::uuid(),
                'code'                => 'product_page_inline_1',
                'name'                => 'Product Page – Inline Banner 1',
                'description'         => 'First inline banner in the product detail centre column (between warranty and bought-together sections).',
                'width_px'            => 760,
                'height_px'           => 380,
                'mobile_width_px'     => 375,
                'mobile_height_px'    => 190,
                'max_file_size_kb'    => 256,
                'allowed_formats'     => json_encode(['jpg', 'jpeg', 'png', 'webp']),
                'device_restriction'  => 'all',
                'max_simultaneous'    => 1,
                'supports_vendor_ads' => 0,
                'is_active'           => 1,
                'sort_order'          => 140,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'id'                  => (string) \Illuminate\Support\Str::uuid(),
                'code'                => 'product_page_inline_2',
                'name'                => 'Product Page – Inline Banner 2',
                'description'         => 'Second inline banner in the product detail centre column.',
                'width_px'            => 760,
                'height_px'           => 380,
                'mobile_width_px'     => 375,
                'mobile_height_px'    => 190,
                'max_file_size_kb'    => 256,
                'allowed_formats'     => json_encode(['jpg', 'jpeg', 'png', 'webp']),
                'device_restriction'  => 'all',
                'max_simultaneous'    => 1,
                'supports_vendor_ads' => 0,
                'is_active'           => 1,
                'sort_order'          => 150,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
        ]);
    }

    public function down(): void
    {
        BannerPlacementDefinition::whereIn('code', ['product_page_inline_1', 'product_page_inline_2'])->delete();

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
                'email_header'
            ) NOT NULL
        ");
    }
};
