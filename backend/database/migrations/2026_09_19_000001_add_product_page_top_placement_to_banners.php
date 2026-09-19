<?php

use App\Models\BannerPlacementDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BASE = [
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
        'gift_cards_redeem',
    ];

    public function up(): void
    {
        $this->setEnum([...self::BASE, 'product_page_top']);

        BannerPlacementDefinition::insert([[
            'id'                  => (string) \Illuminate\Support\Str::uuid(),
            'code'                => 'product_page_top',
            'name'                => 'Product Page – Top',
            'description'         => 'Full-width banner under the breadcrumb at the top of product detail pages.',
            'width_px'            => 1200,
            'height_px'           => 150,
            'mobile_width_px'     => 375,
            'mobile_height_px'    => 100,
            'max_file_size_kb'    => 256,
            'allowed_formats'     => json_encode(['jpg', 'jpeg', 'png', 'webp']),
            'device_restriction'  => 'all',
            'max_simultaneous'    => 1,
            'supports_vendor_ads' => 1,
            'base_rate_weekly'    => 15000,
            'is_active'           => 1,
            'sort_order'          => 170,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]]);
    }

    public function down(): void
    {
        BannerPlacementDefinition::where('code', 'product_page_top')->delete();
        $this->setEnum(self::BASE);
    }

    private function setEnum(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));
        DB::statement("ALTER TABLE banners MODIFY COLUMN placement_code ENUM({$list}) NOT NULL");
    }
};
