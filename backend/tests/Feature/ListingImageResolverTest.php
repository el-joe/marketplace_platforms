<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Services\Media\ListingImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-17: the resolver is the single source of truth for
 * "what images does this listing/variant show" — variant images first,
 * product-level fallback only when the variant has none, never mixed,
 * never another variant's images. Also proves the batching contract (two
 * image queries regardless of how many variants are requested).
 */
class ListingImageResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_with_images_returns_only_its_own_images(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantWithImages = $scenario->variants[0];

        $resolver = app(ListingImageResolver::class);
        $images = $resolver->gallery($variantWithImages->id);

        $this->assertCount(2, $images);
        $this->assertStringContainsString('variant-black-1.jpg', $images[0]->url);
        $this->assertTrue($images[0]->isPrimary);
        $this->assertStringContainsString('variant-black-2.jpg', $images[1]->url);
    }

    public function test_variant_without_images_falls_back_to_product_level_images(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantWithoutImages = $scenario->variants[1];

        // Add a product-level (variant-agnostic) fallback image.
        ProductImage::create([
            'product_id'         => $scenario->product->id,
            'product_variant_id' => null,
            'path'               => 'products/product-level.jpg',
            'disk'               => 'public',
            'position'           => 0,
            'is_primary'         => true,
        ]);

        $resolver = app(ListingImageResolver::class);
        $images = $resolver->gallery($variantWithoutImages->id);

        $this->assertCount(1, $images);
        $this->assertStringContainsString('product-level.jpg', $images[0]->url);
    }

    public function test_variant_a_images_never_leak_into_variant_b_card(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantWithImages = $scenario->variants[0];
        $variantWithoutImages = $scenario->variants[1];

        ProductImage::create([
            'product_id'         => $scenario->product->id,
            'product_variant_id' => null,
            'path'               => 'products/product-level-fallback.jpg',
            'disk'               => 'public',
            'position'           => 0,
            'is_primary'         => true,
        ]);

        $resolver = app(ListingImageResolver::class);
        $imagesA = $resolver->gallery($variantWithImages->id);
        $imagesB = $resolver->gallery($variantWithoutImages->id);

        // A keeps its own images.
        $this->assertStringContainsString('variant-black-1.jpg', $imagesA[0]->url);
        // B never shows A's images — only the product-level fallback.
        foreach ($imagesB as $image) {
            $this->assertStringNotContainsString('variant-black', $image->url);
        }
        $this->assertStringContainsString('product-level-fallback.jpg', $imagesB[0]->url);
    }

    public function test_no_images_and_no_fallback_returns_empty(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantWithoutImages = $scenario->variants[1];

        $resolver = app(ListingImageResolver::class);
        $images = $resolver->gallery($variantWithoutImages->id);

        $this->assertSame([], $images);
        $this->assertNull($resolver->primary($variantWithoutImages->id));
    }

    public function test_forvariants_issues_at_most_two_image_queries_regardless_of_variant_count(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        // Create several more variants, some with images, some without,
        // so the batch has a realistic mix.
        $variantIds = [$scenario->variants[0]->id, $scenario->variants[1]->id];
        for ($i = 0; $i < 8; $i++) {
            $variant = \App\Models\ProductVariant::create([
                'product_id' => $scenario->product->id,
                'sku' => 'SKU-EXTRA-' . $i . '-' . \Illuminate\Support\Str::random(6),
                'variant_name' => "Extra {$i}",
                'is_default' => false,
                'is_active' => true,
                'position' => $i + 2,
            ]);
            $variantIds[] = $variant->id;

            if ($i % 2 === 0) {
                ProductImage::create([
                    'product_id' => $scenario->product->id,
                    'product_variant_id' => $variant->id,
                    'path' => "products/extra-{$i}.jpg",
                    'disk' => 'public',
                    'position' => 0,
                    'is_primary' => true,
                ]);
            }
        }

        $resolver = app(ListingImageResolver::class);

        DB::enableQueryLog();
        $result = $resolver->forVariants($variantIds);
        $imageQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'product_images') || str_contains($q['query'], 'product_variants'))
            ->count();
        DB::disableQueryLog();

        $this->assertCount(10, $result);
        // 1 non-image lookup (variant -> product_id) + 1 variant-image query
        // + 1 product-fallback-image query = 3 total supporting/image
        // queries for the whole batch, never one per variant.
        $this->assertLessThanOrEqual(3, $imageQueries);
    }

    public function test_forvariants_memoizes_within_a_request_no_repeat_queries(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantId = $scenario->variants[0]->id;

        $resolver = app(ListingImageResolver::class);
        $resolver->gallery($variantId);

        DB::enableQueryLog();
        $resolver->gallery($variantId);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries);
    }

    public function test_absolute_urls_are_returned(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantId = $scenario->variants[0]->id;

        $resolver = app(ListingImageResolver::class);
        $url = $resolver->primary($variantId);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('http', $url);
    }

    public function test_observer_invalidates_cache_on_new_variant_image(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $variantId = $scenario->variants[1]->id; // starts with no images

        $resolver = app(ListingImageResolver::class);
        $this->assertNull($resolver->primary($variantId));

        ProductImage::create([
            'product_id' => $scenario->product->id,
            'product_variant_id' => $variantId,
            'path' => 'products/newly-added.jpg',
            'disk' => 'public',
            'position' => 0,
            'is_primary' => true,
        ]);

        // A fresh resolver instance (new request-scoped memo) must see the
        // new image — the per-variant cache key must have been invalidated
        // by ProductImageObserver, not left stale.
        $freshResolver = new ListingImageResolver();
        $url = $freshResolver->primary($variantId);

        $this->assertNotNull($url);
        $this->assertStringContainsString('newly-added.jpg', $url);
    }
}
