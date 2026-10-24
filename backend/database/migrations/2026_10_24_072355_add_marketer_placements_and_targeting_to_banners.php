<?php

use App\Models\BannerPlacementDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        'product_page_top',
    ];

    private const NEW = ['marketers_list_hero', 'marketers_list_strip', 'marketer_profile_slider'];

    public function up(): void
    {
        $this->setEnum([...self::BASE, ...self::NEW]);

        Schema::table('banners', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('priority');
            $table->enum('gender_scope', ['all', 'male', 'female'])->default('all')->after('is_global');
        });

        $common = [
            'allowed_formats' => json_encode(['jpg', 'jpeg', 'png', 'webp', 'avif']),
            'device_restriction' => 'all',
            'supports_vendor_ads' => 0,
            'base_rate_weekly' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        BannerPlacementDefinition::insert([
            $common + [
                'id' => (string) Str::uuid(),
                'code' => 'marketers_list_hero',
                'name' => 'Marketers List – Hero Slider',
                'description' => 'Top hero slider on the marketers directory page (recommended 1700×450).',
                'width_px' => 1700,
                'height_px' => 450,
                'mobile_width_px' => 750,
                'mobile_height_px' => 400,
                'max_file_size_kb' => 512,
                'max_simultaneous' => 10,
                'sort_order' => 180,
            ],
            $common + [
                'id' => (string) Str::uuid(),
                'code' => 'marketers_list_strip',
                'name' => 'Marketers List – Ad Strip',
                'description' => 'Two-column ad strip on the marketers directory page (recommended 840×300 each).',
                'width_px' => 840,
                'height_px' => 300,
                'mobile_width_px' => 750,
                'mobile_height_px' => 300,
                'max_file_size_kb' => 384,
                'max_simultaneous' => 2,
                'sort_order' => 190,
            ],
            $common + [
                'id' => (string) Str::uuid(),
                'code' => 'marketer_profile_slider',
                'name' => 'Marketer Profile – Banner Slider',
                'description' => 'Banner carousel inside marketer details pages (recommended 1000×280).',
                'width_px' => 1000,
                'height_px' => 280,
                'mobile_width_px' => 750,
                'mobile_height_px' => 300,
                'max_file_size_kb' => 384,
                'max_simultaneous' => 10,
                'sort_order' => 200,
            ],
        ]);
    }

    public function down(): void
    {
        BannerPlacementDefinition::whereIn('code', self::NEW)->delete();
        DB::table('banners')->whereIn('placement_code', self::NEW)->delete();

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['is_global', 'gender_scope']);
        });

        $this->setEnum(self::BASE);
    }

    private function setEnum(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));
        DB::statement("ALTER TABLE banners MODIFY COLUMN placement_code ENUM({$list}) NOT NULL");
    }
};
