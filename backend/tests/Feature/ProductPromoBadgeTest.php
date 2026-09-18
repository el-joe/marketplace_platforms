<?php

namespace Tests\Feature;

use App\Http\Resources\Customer\ProductDetailResource;
use App\Models\Product;
use App\Models\ProductPromoBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * docs/plans/dynamic-badges-and-classified-actions.md Task A: the
 * product_promo_badges table + products.is_mega_deal flag that drive the
 * PDP/listing-card rotating badge and the "Mega deal" badge, additive to
 * the existing shipping_badge/best_seller_badge fields.
 */
class ProductPromoBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_promo_badges_relation_returns_only_active_badges_in_sort_order(): void
    {
        $product = Product::factory()->create();

        $inactive = ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'Inactive badge',
            'is_active' => false,
            'sort_order' => 0,
        ]);
        $second = ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'Second',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $first = ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'First',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $badges = $product->promoBadges()->get();

        $this->assertCount(2, $badges);
        $this->assertSame([$first->id, $second->id], $badges->pluck('id')->all());
        $this->assertFalse($badges->pluck('id')->contains($inactive->id));
    }

    public function test_product_is_mega_deal_defaults_to_false(): void
    {
        $product = Product::factory()->create();

        $this->assertFalse($product->fresh()->is_mega_deal);
    }

    public function test_product_detail_resource_exposes_promo_badges_and_is_mega_deal_additively(): void
    {
        $product = Product::factory()->create(['is_mega_deal' => true]);
        ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'Free next-day delivery',
            'label_ar' => 'توصيل مجاني في اليوم التالي',
            'icon_key' => 'truck',
            'sort_order' => 0,
        ]);

        $product->load('promoBadges');

        $resource = new ProductDetailResource($product);
        $array = $resource->resolve(Request::create('/'));

        $this->assertTrue($array['is_mega_deal']);
        $this->assertCount(1, $array['promo_badges']);
        $this->assertSame('truck', $array['promo_badges'][0]['icon_key']);
        $this->assertSame('Free next-day delivery', $array['promo_badges'][0]['label']['en']);
        $this->assertSame('توصيل مجاني في اليوم التالي', $array['promo_badges'][0]['label']['ar']);

        // Purely additive: existing fields untouched.
        $this->assertArrayHasKey('has_variants', $array);
        $this->assertArrayHasKey('id', $array);
    }

    public function test_product_detail_resource_promo_badges_absent_when_relation_not_loaded(): void
    {
        $product = Product::factory()->create();

        $resource = new ProductDetailResource($product);
        $array = $resource->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('promo_badges', $array);
        $this->assertFalse($array['is_mega_deal']);
    }
}
