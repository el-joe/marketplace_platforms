<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlockTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $types = [
            // ── Hero ────────────────────────────────────────────────────────
            [
                'code' => 'hero_slider', 'group' => 'hero',
                'label_en' => 'Hero Slider', 'label_ar' => 'عارض الشرائح',
                'icon' => 'squares-2x2', 'max_per_page' => null,
                'config_schema' => ['height_desktop', 'autoplay_seconds', 'show_dots', 'show_arrows', 'loop', 'transition'],
                'default_config' => ['height_desktop' => '480px', 'autoplay_seconds' => 4, 'show_dots' => true, 'show_arrows' => true, 'loop' => true],
            ],
            [
                'code' => 'countdown_deal', 'group' => 'hero',
                'label_en' => 'Countdown Deal', 'label_ar' => 'صفقة العد التنازلي',
                'icon' => 'clock', 'max_per_page' => 2,
                'config_schema' => ['title_en', 'title_ar', 'ends_at', 'background_color', 'text_color'],
                'default_config' => [],
            ],
            [
                'code' => 'video_banner', 'group' => 'hero',
                'label_en' => 'Video Banner', 'label_ar' => 'بانر فيديو',
                'icon' => 'play-circle', 'max_per_page' => 3,
                'config_schema' => ['video_url', 'poster_url', 'autoplay', 'muted'],
                'default_config' => ['autoplay' => true, 'muted' => true],
            ],

            // ── Products ────────────────────────────────────────────────────
            [
                'code' => 'product_row', 'group' => 'products',
                'label_en' => 'Product Row', 'label_ar' => 'صف المنتجات',
                'icon' => 'squares-plus', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'source', 'category_id', 'flash_sale_id', 'items_per_row', 'max_products', 'show_view_all', 'scrollable_row', 'show_ratings', 'show_discount_badge'],
                'default_config' => ['source' => 'best_sellers', 'items_per_row' => 4, 'max_products' => 12, 'show_view_all' => true],
            ],
            [
                'code' => 'flash_sale', 'group' => 'products',
                'label_en' => 'Flash Sale', 'label_ar' => 'تخفيضات سريعة',
                'icon' => 'bolt', 'max_per_page' => 3,
                'config_schema' => ['flash_sale_id', 'max_items_shown', 'items_per_row', 'show_countdown', 'show_stock_bar', 'background_color', 'badge_label_en', 'badge_label_ar'],
                'default_config' => ['max_items_shown' => 8, 'items_per_row' => 4, 'show_countdown' => true, 'show_stock_bar' => true],
            ],
            [
                'code' => 'deal_of_day', 'group' => 'products',
                'label_en' => 'Deal of the Day', 'label_ar' => 'صفقة اليوم',
                'icon' => 'star', 'max_per_page' => 2,
                'config_schema' => ['title_en', 'title_ar', 'vendor_listing_id', 'ends_at'],
                'default_config' => [],
            ],
            [
                'code' => 'mega_deals', 'group' => 'products',
                'label_en' => 'Mega Deals', 'label_ar' => 'عروض ميجا',
                'icon' => 'fire', 'max_per_page' => 2,
                'config_schema' => ['title_en', 'title_ar', 'ends_at', 'show_countdown', 'show_view_all', 'columns'],
                'default_config' => ['show_countdown' => true, 'columns' => 2, 'show_view_all' => true],
            ],

            // ── Ads & Banners ───────────────────────────────────────────────
            [
                'code' => 'ad_images_2col', 'group' => 'ads_banners',
                'label_en' => 'Ad Images (2-col)', 'label_ar' => 'صور إعلانية عمودان',
                'icon' => 'view-columns', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'aspect_ratio'],
                'default_config' => ['aspect_ratio' => '4:3'],
            ],
            [
                'code' => 'ad_images_4col', 'group' => 'ads_banners',
                'label_en' => 'Ad Images (4-col)', 'label_ar' => 'صور إعلانية أربعة أعمدة',
                'icon' => 'squares-2x2', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'aspect_ratio'],
                'default_config' => ['aspect_ratio' => '1:1'],
            ],
            [
                'code' => 'image_slider', 'group' => 'ads_banners',
                'label_en' => 'Image Slider / Grid', 'label_ar' => 'سلايدر صور / شبكة',
                'icon' => 'photo', 'max_per_page' => null,
                'config_schema' => [
                    'title_en', 'title_ar',
                    'columns',
                    'rows',
                    'scrollable',
                    'show_label',
                    'show_badge',
                    'image_shape',
                    'size_preset',
                    'aspect_ratio',
                    'background_color',
                ],
                'default_config' => [
                    'columns' => 5,
                    'rows' => 1,
                    'scrollable' => true,
                    'show_label' => true,
                    'show_badge' => false,
                    'image_shape' => 'rounded',
                    'size_preset' => 'medium',
                    'aspect_ratio' => '',
                ],
            ],
            [
                'code' => 'full_banner', 'group' => 'ads_banners',
                'label_en' => 'Full Banner', 'label_ar' => 'بانر كامل العرض',
                'icon' => 'photo', 'max_per_page' => null,
                'config_schema' => ['link_url', 'link_type', 'aspect_ratio', 'mobile_aspect_ratio'],
                'default_config' => ['aspect_ratio' => '4:1', 'mobile_aspect_ratio' => '2:1'],
            ],
            [
                'code' => 'promo_tiles', 'group' => 'ads_banners',
                'label_en' => 'Promo Tiles', 'label_ar' => 'بلاطات ترويجية',
                'icon' => 'squares-2x2', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'grid_cols', 'grid_rows', 'tiles'],
                'default_config' => ['grid_cols' => 2, 'grid_rows' => 2, 'tiles' => []],
            ],
            // ── Discovery ───────────────────────────────────────────────────
            [
                'code' => 'category_pills', 'group' => 'discovery',
                'label_en' => 'Category Pills', 'label_ar' => 'أزرار الفئات',
                'icon' => 'tag', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'show_product_count', 'max_items'],
                'default_config' => ['max_items' => 12, 'show_product_count' => true],
            ],
            [
                'code' => 'brand_strip', 'group' => 'discovery',
                'label_en' => 'Brand Strip', 'label_ar' => 'شريط العلامات التجارية',
                'icon' => 'building-storefront', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'max_items', 'show_logo_only'],
                'default_config' => ['max_items' => 10, 'show_logo_only' => true],
            ],
            [
                'code' => 'search_trends', 'group' => 'discovery',
                'label_en' => 'Search Trends', 'label_ar' => 'توجهات البحث',
                'icon' => 'magnifying-glass', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'max_terms'],
                'default_config' => ['max_terms' => 8],
            ],

            // ── Engagement ──────────────────────────────────────────────────
            [
                'code' => 'countdown_timer', 'group' => 'engagement',
                'label_en' => 'Countdown Timer', 'label_ar' => 'مؤقت العد التنازلي',
                'icon' => 'clock', 'max_per_page' => null,
                'config_schema' => ['title_en', 'title_ar', 'ends_at', 'background_color', 'text_color'],
                'default_config' => [],
            ],
            [
                'code' => 'text_block', 'group' => 'engagement',
                'label_en' => 'Text Block', 'label_ar' => 'كتلة نصية',
                'icon' => 'document-text', 'max_per_page' => 10,
                'config_schema' => ['content_html_en', 'content_html_ar', 'text_align', 'max_width'],
                'default_config' => ['text_align' => 'left', 'max_width' => '1200px'],
            ],
            [
                'code' => 'divider', 'group' => 'engagement',
                'label_en' => 'Divider / Spacer', 'label_ar' => 'فاصل',
                'icon' => 'minus', 'max_per_page' => 20,
                'config_schema' => ['style', 'color', 'margin_top', 'margin_bottom'],
                'default_config' => ['style' => 'solid', 'color' => '#e5e7eb', 'margin_top' => 16, 'margin_bottom' => 16],
            ],
            [
                'code' => 'newsletter_signup', 'group' => 'engagement',
                'label_en' => 'Newsletter Signup', 'label_ar' => 'الاشتراك بالنشرة',
                'icon' => 'envelope', 'max_per_page' => 2,
                'config_schema' => ['title_en', 'title_ar', 'subtitle_en', 'subtitle_ar'],
                'default_config' => [],
            ],
        ];

        // Truncate to reseed exactly the spec set (existing referencing
        // page_blocks.block_type rows aren't FK-constrained against this table
        // because page_blocks.block_type stores the code as a string).
        DB::table('block_types')->delete();

        $sortOrder = 0;
        $rows = [];
        foreach ($types as $type) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'code' => $type['code'],
                'label_en' => $type['label_en'],
                'label_ar' => $type['label_ar'],
                'group' => $type['group'],
                'icon' => $type['icon'],
                'description_en' => null,
                'description_ar' => null,
                'config_schema' => json_encode($type['config_schema']),
                'default_config' => json_encode((object) $type['default_config']),
                'is_active' => true,
                'requires_permission' => $type['requires_permission'] ?? null,
                'max_per_page' => $type['max_per_page'] ?? null,
                'sort_order' => $sortOrder++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('block_types')->insert($rows);
    }
}
