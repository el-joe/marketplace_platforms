<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageBlockProduct;
use App\Models\PageSection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Shared\PageBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/plans/mega-deal-page-builder-correction.md Task F: "Mega deal" is
 * computed live from the existing Page Builder `mega_deals` block type
 * (page_blocks.block_type = 'mega_deals' -> page_block_products ->
 * product_variants -> products) instead of the removed flat
 * `products.is_mega_deal` column. This exercises
 * PageBuilderService::activeMegaDealProductIds()/isProductInActiveMegaDeal()
 * directly, and the same visibility rule buildSkeleton() uses to decide
 * whether a block is "active" (is_visible, visible_from/visible_until,
 * country_override).
 */
class PageBuilderServiceMegaDealTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create([
            'id'            => (string) Str::uuid(),
            'iso_code_2'    => 'AE',
            'iso_code_3'    => 'ARE',
            'name_ar'       => 'الإمارات',
            'name_en'       => 'United Arab Emirates - ' . Str::random(6),
            'currency_code' => 'AED',
            'vat_rate'      => 5.00,
            'is_active'     => true,
            'is_launched'   => true,
            'cod_available' => true,
            'timezone'      => 'Asia/Dubai',
        ]);
    }

    private function makeMegaDealBlock(array $overrides = []): PageBlock
    {
        $page = Page::create([
            'country_id' => $this->country->id,
            'page_type'  => 'home',
            'name'       => 'Home',
            'slug'       => 'home-' . Str::random(6),
            'status'     => 'published',
            'version'    => 1,
            'is_default' => false,
        ]);

        $section = PageSection::create([
            'page_id'    => $page->id,
            'name'       => 'Main',
            'position'   => 0,
            'is_visible' => true,
            'layout'     => 'stack',
        ]);

        return PageBlock::create(array_merge([
            'page_id'             => $page->id,
            'section_id'          => $section->id,
            'column_index'        => 0,
            'block_type'          => 'mega_deals',
            'position'            => 0,
            'config'              => [],
            'is_visible'          => true,
            'device_target'       => 'all',
            'audience'            => 'all',
            'cache_ttl_seconds'   => 60,
            'created_by_admin_id' => (string) Str::uuid(),
        ], $overrides));
    }

    private function attachProduct(PageBlock $block, Product $product, int $position = 0): void
    {
        $variant = ProductVariant::factory()->for($product)->create();

        PageBlockProduct::create([
            'page_block_id'      => $block->id,
            'product_variant_id' => $variant->id,
            'position'           => $position,
            'added_by_admin_id'  => (string) Str::uuid(),
        ]);
    }

    public function test_product_in_active_visible_mega_deals_block_is_true(): void
    {
        $product = Product::factory()->create();
        $block = $this->makeMegaDealBlock();
        $this->attachProduct($block, $product);

        $service = app(PageBuilderService::class);

        $this->assertTrue($service->isProductInActiveMegaDeal($product->id, $this->country));
        $this->assertTrue(
            $service->activeMegaDealProductIds([$product->id], $this->country)->contains($product->id)
        );
    }

    public function test_product_not_in_any_mega_deals_block_is_false(): void
    {
        $product = Product::factory()->create();
        $service = app(PageBuilderService::class);

        $this->assertFalse($service->isProductInActiveMegaDeal($product->id, $this->country));
    }

    public function test_product_only_in_invisible_block_is_false(): void
    {
        $product = Product::factory()->create();
        $block = $this->makeMegaDealBlock(['is_visible' => false]);
        $this->attachProduct($block, $product);

        $service = app(PageBuilderService::class);

        $this->assertFalse($service->isProductInActiveMegaDeal($product->id, $this->country));
    }

    public function test_product_only_in_expired_block_is_false(): void
    {
        $product = Product::factory()->create();
        $block = $this->makeMegaDealBlock([
            'visible_from'  => now()->subDays(10),
            'visible_until' => now()->subDay(),
        ]);
        $this->attachProduct($block, $product);

        $service = app(PageBuilderService::class);

        $this->assertFalse($service->isProductInActiveMegaDeal($product->id, $this->country));
    }

    public function test_product_only_in_future_block_is_false(): void
    {
        $product = Product::factory()->create();
        $block = $this->makeMegaDealBlock([
            'visible_from' => now()->addDay(),
        ]);
        $this->attachProduct($block, $product);

        $service = app(PageBuilderService::class);

        $this->assertFalse($service->isProductInActiveMegaDeal($product->id, $this->country));
    }

    public function test_product_only_in_other_country_override_block_is_false(): void
    {
        $product = Product::factory()->create();
        $otherCountry = Country::create([
            'id'            => (string) Str::uuid(),
            'iso_code_2'    => 'SA',
            'iso_code_3'    => 'SAU',
            'name_ar'       => 'السعودية',
            'name_en'       => 'Saudi Arabia - ' . Str::random(6),
            'currency_code' => 'SAR',
            'vat_rate'      => 15.00,
            'is_active'     => true,
            'is_launched'   => true,
            'cod_available' => true,
            'timezone'      => 'Asia/Riyadh',
        ]);

        $block = $this->makeMegaDealBlock(['country_override' => $otherCountry->id]);
        $this->attachProduct($block, $product);

        $service = app(PageBuilderService::class);

        $this->assertFalse($service->isProductInActiveMegaDeal($product->id, $this->country));
        $this->assertTrue($service->isProductInActiveMegaDeal($product->id, $otherCountry));
    }
}
