<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomePageSeeder extends Seeder
{
    /**
     * Fallback brand colors per ISO country code — not modeled in the DB.
     */
    private const COUNTRY_COLORS = [
        'ae' => '#C8102E',
        'sa' => '#006C35',
        'eg' => '#CE1126',
        'kw' => '#007A3D',
        'qa' => '#8D1B3D',
        'bh' => '#CE1126',
        'om' => '#DB161B',
        'jo' => '#007A3D',
    ];

    private const DEFAULT_COLOR = '#111827';

    private string $adminId;

    private array $categoryIds;

    private array $variantIds;

    private array $vendorIds;

    private array $countries;

    public function run(): void
    {
        $this->loadReferenceData();

        DB::transaction(function () {
            foreach ($this->countries as $code => $country) {
                $this->buildHomePage($code, $country);
            }
        });

        $this->command->info('HomePageSeeder: done for ' . count($this->countries) . ' countries.');
    }

    private const REQUIRED_CATEGORY_SLUGS = ['electronics', 'fashion', 'beauty', 'home', 'grocery', 'health', 'sports', 'toys', 'books', 'automotive'];

    private const REQUIRED_VARIANT_COUNT = 15;

    private const REQUIRED_VENDOR_COUNT = 6;

    private function loadReferenceData(): void
    {
        $admin = DB::table('admins')->orderBy('created_at')->first();
        if (!$admin) {
            $admin = Admin::factory()->create();
            $this->command->info('HomePageSeeder: no admin found, created one via factory.');
        }
        $this->adminId = $admin->id;

        if (!DB::table('countries')->exists()) {
            Country::factory()->create([
                'iso_code_2'    => 'AE',
                'iso_code_3'    => 'ARE',
                'name_en'       => 'United Arab Emirates',
                'name_ar'       => 'الإمارات العربية المتحدة',
                'currency_code' => 'AED',
            ]);
            $this->command->info('HomePageSeeder: no countries found, created a default one via factory.');
        }

        $this->countries = DB::table('countries')
            ->orderBy('name_en')
            ->get()
            ->mapWithKeys(function ($country) {
                $code = strtolower($country->iso_code_2);

                return [$code => [
                    'id'       => $country->id,
                    'name'     => $country->name_en,
                    'currency' => $country->currency_code,
                    'color'    => self::COUNTRY_COLORS[$code] ?? self::DEFAULT_COLOR,
                ]];
            })
            ->all();

        $existingSlugs = DB::table('categories')->whereIn('slug', self::REQUIRED_CATEGORY_SLUGS)->pluck('slug')->all();
        foreach (array_diff(self::REQUIRED_CATEGORY_SLUGS, $existingSlugs) as $missingSlug) {
            Category::factory()->create([
                'slug'    => $missingSlug,
                'name_en' => ucfirst($missingSlug),
                'name_ar' => $missingSlug,
            ]);
        }
        if ($missing = array_diff(self::REQUIRED_CATEGORY_SLUGS, $existingSlugs)) {
            $this->command->info('HomePageSeeder: created missing categories via factory: ' . implode(', ', $missing));
        }

        $this->categoryIds = DB::table('categories')
            ->whereIn('slug', self::REQUIRED_CATEGORY_SLUGS)
            ->pluck('id', 'slug')
            ->all();

        $variantCount = DB::table('product_variants')->where('is_active', true)->count();
        if ($variantCount < self::REQUIRED_VARIANT_COUNT) {
            $toCreate = self::REQUIRED_VARIANT_COUNT - $variantCount;
            ProductVariant::factory()->count($toCreate)->create();
            $this->command->info("HomePageSeeder: created {$toCreate} product variant(s) via factory.");
        }

        $this->variantIds = DB::table('product_variants')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->limit(self::REQUIRED_VARIANT_COUNT)
            ->pluck('id')
            ->all();

        $vendorCount = DB::table('vendors')->where('global_status', 'active')->count();
        if ($vendorCount < self::REQUIRED_VENDOR_COUNT) {
            $toCreate = self::REQUIRED_VENDOR_COUNT - $vendorCount;
            Vendor::factory()->count($toCreate)->create();
            $this->command->info("HomePageSeeder: created {$toCreate} vendor(s) via factory.");
        }

        $this->vendorIds = DB::table('vendors')
            ->where('global_status', 'active')
            ->orderBy('created_at')
            ->limit(self::REQUIRED_VENDOR_COUNT)
            ->pluck('id')
            ->all();
    }

    private function buildHomePage(string $code, array $country): void
    {
        $now    = now();
        $pageId = (string) Str::uuid();
        $cats   = $this->categoryIds;
        $vars   = $this->variantIds;
        $vends  = $this->vendorIds;
        $color  = $country['color'];
        $name   = $country['name'];
        $saleEnds = now()->addDays(3)->toDateTimeString();
        $nextSale = now()->addDays(7)->toDateTimeString();
        $eod      = now()->endOfDay()->toDateTimeString();

        // Step 1: Delete any existing home page for this country
        $this->deleteExistingHomePage($country['id']);

        // Step 2: Insert the page record
        $this->insertPage($pageId, $country, $now);

        // Step 3: Define all blocks in order
        $blockDefs = [

            // ── Position 0: HERO SLIDER ─────────────────────────────────────────────
            [
                'type'   => 'hero_slider',
                'config' => [
                    'loop'             => true,
                    'show_dots'        => true,
                    'transition'       => 'slide',
                    'show_arrows'      => true,
                    'height_desktop'   => '480px',
                    'autoplay_seconds' => 4,
                ],
            ],

            // ── Position 1: COUNTDOWN DEAL ──────────────────────────────────────────
            [
                'type'   => 'countdown_deal',
                'config' => [
                    'title_en'         => 'Mega Sale — Ends Soon!',
                    'title_ar'         => 'تخفيضات ضخمة — تنتهي قريبًا!',
                    'ends_at'          => $saleEnds,
                    'background_color' => $color,
                    'text_color'       => '#ffffff',
                ],
            ],

            // ── Position 2: FLASH SALE ──────────────────────────────────────────────
            [
                'type'   => 'flash_sale',
                'config' => [
                    'flash_sale_id'    => null,
                    'max_items_shown'  => 8,
                    'items_per_row'    => 4,
                    'show_countdown'   => true,
                    'show_stock_bar'   => true,
                    'background_color' => '#fef2f2',
                    'badge_label_en'   => 'Flash Sale',
                    'badge_label_ar'   => 'تخفيض سريع',
                ],
            ],

            // ── Position 3: FULL BANNER ─────────────────────────────────────────────
            [
                'type'   => 'full_banner',
                'config' => [
                    'link_url'            => '/' . $code . '/sale',
                    'link_type'           => 'url',
                    'aspect_ratio'        => '4:1',
                    'mobile_aspect_ratio' => '2:1',
                ],
            ],

            // ── Position 4: PRODUCT ROW — Best Sellers ──────────────────────────────
            [
                'type'   => 'product_row',
                'config' => [
                    'title_en'            => 'Best Sellers',
                    'title_ar'            => 'الأكثر مبيعًا',
                    'source'              => 'best_sellers',
                    'category_id'         => null,
                    'flash_sale_id'       => null,
                    'items_per_row'       => 4,
                    'max_products'        => 12,
                    'show_view_all'       => true,
                    'scrollable_row'      => true,
                    'show_ratings'        => true,
                    'show_discount_badge' => true,
                ],
            ],

            // ── Position 5: CATEGORY PILLS ──────────────────────────────────────────
            [
                'type'       => 'category_pills',
                'config'     => [
                    'title_en'           => 'Shop by Category',
                    'title_ar'           => 'تسوق حسب الفئة',
                    'show_product_count' => true,
                    'max_items'          => 10,
                ],
                'categories' => [
                    $cats['electronics'],
                    $cats['fashion'],
                    $cats['beauty'],
                    $cats['home'],
                    $cats['grocery'],
                    $cats['health'],
                    $cats['sports'],
                    $cats['toys'],
                    $cats['books'],
                    $cats['automotive'],
                ],
            ],

            // ── Position 6: AD IMAGES (2-col) ────────────────────────────────────────
            [
                'type'   => 'ad_images_2col',
                'config' => [
                    'title_en'     => "Today's Top Picks",
                    'title_ar'     => 'أبرز اختيارات اليوم',
                    'aspect_ratio' => '4:3',
                ],
            ],

            // ── Position 7: PRODUCT ROW — New Arrivals ───────────────────────────────
            [
                'type'   => 'product_row',
                'config' => [
                    'title_en'            => 'New Arrivals',
                    'title_ar'            => 'وصل حديثًا',
                    'source'              => 'new_arrivals',
                    'category_id'         => null,
                    'flash_sale_id'       => null,
                    'items_per_row'       => 4,
                    'max_products'        => 12,
                    'show_view_all'       => true,
                    'scrollable_row'      => true,
                    'show_ratings'        => true,
                    'show_discount_badge' => false,
                ],
            ],

            // ── Position 8: DEAL OF THE DAY ──────────────────────────────────────────
            [
                'type'   => 'deal_of_day',
                'config' => [
                    'title_en'          => 'Deal of the Day',
                    'title_ar'          => 'صفقة اليوم',
                    'vendor_listing_id' => null,
                    'ends_at'           => $eod,
                ],
            ],

            // ── Position 9: DIVIDER ──────────────────────────────────────────────────
            [
                'type'   => 'divider',
                'config' => [
                    'style'         => 'solid',
                    'color'         => '#e5e7eb',
                    'margin_top'    => 16,
                    'margin_bottom' => 16,
                ],
            ],

            // ── Position 10: AD IMAGES (4-col) ───────────────────────────────────────
            [
                'type'   => 'ad_images_4col',
                'config' => [
                    'title_en'     => 'Explore Our Collections',
                    'title_ar'     => 'استكشف مجموعاتنا',
                    'aspect_ratio' => '1:1',
                ],
            ],

            // ── Position 11: PRODUCT ROW — Featured ──────────────────────────────────
            [
                'type'   => 'product_row',
                'config' => [
                    'title_en'            => 'Featured Products',
                    'title_ar'            => 'منتجات مميزة',
                    'source'              => 'featured',
                    'category_id'         => null,
                    'flash_sale_id'       => null,
                    'items_per_row'       => 4,
                    'max_products'        => 8,
                    'show_view_all'       => true,
                    'scrollable_row'      => true,
                    'show_ratings'        => true,
                    'show_discount_badge' => true,
                ],
            ],

            // ── Position 12: PRODUCT ROW — Manual picks ───────────────────────────────
            [
                'type'     => 'product_row',
                'config'   => [
                    'title_en'            => 'Hand-Picked for You',
                    'title_ar'            => 'اختيارات خاصة لك',
                    'source'              => 'manual',
                    'category_id'         => null,
                    'flash_sale_id'       => null,
                    'items_per_row'       => 4,
                    'max_products'        => 12,
                    'show_view_all'       => false,
                    'scrollable_row'      => true,
                    'show_ratings'        => true,
                    'show_discount_badge' => true,
                ],
                'products' => [
                    $vars[0], $vars[1], $vars[2], $vars[3],
                    $vars[4], $vars[5], $vars[6], $vars[7],
                ],
            ],

            // ── Position 13: BRAND STRIP ─────────────────────────────────────────────
            [
                'type'    => 'brand_strip',
                'config'  => [
                    'title_en'       => 'Top Brands',
                    'title_ar'       => 'أبرز العلامات التجارية',
                    'max_items'      => 6,
                    'show_logo_only' => true,
                ],
                'sellers' => $vends,
            ],

            // ── Position 14: SEARCH TRENDS ───────────────────────────────────────────
            [
                'type'   => 'search_trends',
                'config' => [
                    'title_en'  => 'Trending Now',
                    'title_ar'  => 'الأكثر بحثًا الآن',
                    'max_terms' => 8,
                ],
            ],

            // ── Position 16: VIDEO BANNER ─────────────────────────────────────────────
            [
                'type'   => 'video_banner',
                'config' => [
                    'video_url'  => null,
                    'poster_url' => null,
                    'autoplay'   => true,
                    'muted'      => true,
                ],
            ],

            // ── Position 17: PRODUCT ROW — Top Rated ─────────────────────────────────
            [
                'type'   => 'product_row',
                'config' => [
                    'title_en'            => 'Top Rated',
                    'title_ar'            => 'الأعلى تقييمًا',
                    'source'              => 'top_rated',
                    'category_id'         => null,
                    'flash_sale_id'       => null,
                    'items_per_row'       => 4,
                    'max_products'        => 12,
                    'show_view_all'       => true,
                    'scrollable_row'      => true,
                    'show_ratings'        => true,
                    'show_discount_badge' => true,
                ],
            ],

            // ── Position 18: COUNTDOWN TIMER ─────────────────────────────────────────
            [
                'type'   => 'countdown_timer',
                'config' => [
                    'title_en'         => 'Next Big Sale Starts In',
                    'title_ar'         => 'التخفيضات الكبرى القادمة تبدأ بعد',
                    'ends_at'          => $nextSale,
                    'background_color' => '#111827',
                    'text_color'       => '#ffffff',
                ],
            ],

            // ── Position 19: TEXT BLOCK ───────────────────────────────────────────────
            [
                'type'   => 'text_block',
                'config' => [
                    'content_html_en' => '<h2>Shop Smarter in ' . $name . '</h2><p>Discover thousands of products across fashion, electronics, home, beauty, and more — with fast delivery and easy returns.</p>',
                    'content_html_ar' => '<h2>تسوق بذكاء في ' . $name . '</h2><p>اكتشف آلاف المنتجات في الأزياء والإلكترونيات والمنزل والجمال والمزيد — مع توصيل سريع وإرجاع سهل.</p>',
                    'text_align'      => 'center',
                    'max_width'       => '800px',
                ],
            ],

            // ── Position 20: NEWSLETTER SIGNUP ───────────────────────────────────────
            [
                'type'   => 'newsletter_signup',
                'config' => [
                    'title_en'    => 'Get Exclusive Deals First',
                    'title_ar'    => 'احصل على العروض الحصرية أولًا',
                    'subtitle_en' => 'Subscribe to our newsletter and never miss a sale.',
                    'subtitle_ar' => 'اشترك في نشرتنا البريدية ولا تفوّت أي عرض.',
                ],
            ],

            // ── Position 21: DIVIDER (closing spacer) ─────────────────────────────────
            [
                'type'   => 'divider',
                'config' => [
                    'style'         => 'none',
                    'color'         => '#e5e7eb',
                    'margin_top'    => 32,
                    'margin_bottom' => 0,
                ],
            ],

        ]; // end $blockDefs

        // Step 4: Insert each block + its pivots + its revision
        $blocksSnapshot = [];

        foreach ($blockDefs as $position => $def) {
            $row     = $this->buildBlock($def['type'], $def['config'], $pageId, $position, $now);
            $blockId = $row['id'];

            DB::table('page_blocks')->insert($row);

            $this->insertBlockRevision($blockId, $pageId, $def['config'], $position, $now);

            if (!empty($def['categories'])) {
                $this->insertCategoryPivots($blockId, $def['categories'], $now);
            }
            if (!empty($def['products'])) {
                $this->insertProductPivots($blockId, $def['products'], $now);
            }
            if (!empty($def['sellers'])) {
                $this->insertSellerPivots($blockId, $def['sellers'], $now);
            }

            // Accumulate snapshot for page_revisions
            $blocksSnapshot[] = [
                'id'                => $blockId,
                'block_type'        => $def['type'],
                'position'          => $position,
                'config'            => $def['config'],
                'is_visible'        => true,
                'device_target'     => 'all',
                'audience'          => 'all',
                'visible_from'      => null,
                'visible_until'     => null,
                'cache_ttl_seconds' => 300,
            ];
        }

        // Step 5: Insert the page revision snapshot
        $this->insertPageRevision($pageId, $blocksSnapshot, $now);
    }

    private function deleteExistingHomePage(string $countryId): void
    {
        $pageIds = DB::table('pages')
            ->where('country_id', $countryId)
            ->where('page_type', 'home')
            ->pluck('id');

        if ($pageIds->isEmpty()) {
            return;
        }

        $blockIds = DB::table('page_blocks')
            ->whereIn('page_id', $pageIds)
            ->pluck('id');

        DB::table('page_block_revisions')->whereIn('page_block_id', $blockIds)->delete();
        DB::table('page_block_products')->whereIn('page_block_id', $blockIds)->delete();
        DB::table('page_block_categories')->whereIn('page_block_id', $blockIds)->delete();
        DB::table('page_block_sellers')->whereIn('page_block_id', $blockIds)->delete();
        DB::table('page_blocks')->whereIn('page_id', $pageIds)->delete();
        DB::table('page_revisions')->whereIn('page_id', $pageIds)->delete();
        DB::table('page_sections')->whereIn('page_id', $pageIds)->delete();
        DB::table('pages')->whereIn('id', $pageIds)->delete();
    }

    private function insertPage(string $pageId, array $country, $now): void
    {
        DB::table('pages')->insert([
            'id'                      => $pageId,
            'country_id'              => $country['id'],
            'page_type'               => 'home',
            'app_context_key'         => null,
            'reference_id'            => null,
            'name'                    => $country['name'] . ' — Home',
            'slug'                    => 'home',
            'status'                  => 'published',
            'publish_at'              => null,
            'published_at'            => $now,
            'unpublish_at'            => null,
            'published_by_admin_id'   => $this->adminId,
            'last_edited_by_admin_id' => $this->adminId,
            'version'                 => 1,
            'is_default'              => 1,
            'seo_title'               => 'Shop Online in ' . $country['name'] . ' | Nawy',
            'seo_description'         => 'Discover the best deals, flash sales, and top brands in ' . $country['name'] . ' on Nawy.',
            'og_image_url'            => null,
            'created_at'              => $now,
            'updated_at'              => $now,
            'deleted_at'              => null,
        ]);
    }

    private function insertPageRevision(string $pageId, array $blocks, $now): void
    {
        DB::table('page_revisions')->insert([
            'id'                    => (string) Str::uuid(),
            'page_id'               => $pageId,
            'version'               => 1,
            'blocks_snapshot'       => json_encode($blocks),
            'published_by_admin_id' => $this->adminId,
            'publish_reason'        => 'Seeded by HomePageSeeder',
            'created_at'            => $now,
        ]);
    }

    private function buildBlock(string $blockType, array $config, string $pageId, int $position, $now): array
    {
        return [
            'id'                   => (string) Str::uuid(),
            'page_id'              => $pageId,
            'section_id'           => null,
            'block_type'           => $blockType,
            'app_context_key'      => null,
            'position'             => $position,
            'config'               => json_encode($config),
            'is_visible'           => 1,
            'visible_from'         => null,
            'visible_until'        => null,
            'device_target'        => 'all',
            'audience'             => 'all',
            'country_override'     => null,
            'ab_test_id'           => null,
            'ab_variant'           => null,
            'cache_ttl_seconds'    => 300,
            'created_by_admin_id'  => $this->adminId,
            'updated_by_admin_id'  => null,
            'created_at'           => $now,
            'updated_at'           => $now,
            'deleted_at'           => null,
        ];
    }

    private function insertBlockRevision(string $blockId, string $pageId, array $config, int $position, $now): void
    {
        DB::table('page_block_revisions')->insert([
            'id'                  => (string) Str::uuid(),
            'page_block_id'       => $blockId,
            'page_id'             => $pageId,
            'revision_number'     => 1,
            'config_snapshot'     => json_encode($config),
            'is_visible_snapshot' => 1,
            'position_snapshot'   => $position,
            'changed_by_admin_id' => $this->adminId,
            'change_reason'       => 'Seeded by HomePageSeeder',
            'change_type'         => 'created',
            'created_at'          => $now,
        ]);
    }

    private function insertCategoryPivots(string $blockId, array $categoryIds, $now): void
    {
        foreach ($categoryIds as $position => $categoryId) {
            DB::table('page_block_categories')->insert([
                'id'            => (string) Str::uuid(),
                'page_block_id' => $blockId,
                'category_id'   => $categoryId,
                'position'      => $position,
                'created_at'    => $now,
            ]);
        }
    }

    private function insertProductPivots(string $blockId, array $variantIds, $now): void
    {
        foreach ($variantIds as $position => $variantId) {
            DB::table('page_block_products')->insert([
                'id'                 => (string) Str::uuid(),
                'page_block_id'      => $blockId,
                'product_variant_id' => $variantId,
                'position'           => $position,
                'added_by_admin_id'  => $this->adminId,
                'created_at'         => $now,
            ]);
        }
    }

    private function insertSellerPivots(string $blockId, array $vendorIds, $now): void
    {
        foreach ($vendorIds as $position => $vendorId) {
            DB::table('page_block_sellers')->insert([
                'id'            => (string) Str::uuid(),
                'page_block_id' => $blockId,
                'seller_id'     => $vendorId,
                'position'      => $position,
                'created_at'    => $now,
            ]);
        }
    }
}
