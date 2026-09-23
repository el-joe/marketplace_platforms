<?php

namespace Tests\Feature\Coupon;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Services\Admin\CouponService;
use App\Services\Checkout\CheckoutPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CouponTargetingTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(array $extra = []): array
    {
        return array_merge([
            'code' => 'TGT'.random_int(1000, 9999), 'name' => 'T', 'type' => 'percentage', 'value' => 10,
            'currency' => 'AED', 'scope' => 'platform', 'customer_eligibility' => 'all', 'funded_by' => 'platform',
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(), 'is_active' => true,
        ], $extra);
    }

    private function cartLine(MarketplaceScenario $s, bool $marketer = false): CartItem
    {
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        $attrs = ['cart_id' => $cart->id, 'quantity' => 1, 'added_at' => now()];
        if ($marketer) {
            $attrs += ['marketer_listing_id' => $s->marketerListing->id, 'unit_price' => (int) $s->marketerListing->getRawOriginal('price')];
        } else {
            $attrs += ['vendor_listing_id' => $s->vendorListingFbp->id, 'unit_price' => (int) $s->vendorListingFbp->getRawOriginal('price')];
        }

        return CartItem::create($attrs);
    }

    private function apply(Coupon $c, MarketplaceScenario $s, CartItem $i): array
    {
        return app(CheckoutPricingEngine::class)->applyCoupon($c, $s->customer, $i->unit_price, 'AED', [$i]);
    }

    public function test_service_syncs_pivots_on_create_and_update(): void
    {
        $s = MarketplaceScenario::make()->build();
        $admin = Admin::factory()->create();
        $svc = app(CouponService::class);

        $c = $svc->create($this->baseData([
            'vendor_ids' => [$s->vendor->id], 'marketer_ids' => [$s->marketer->id], 'product_ids' => [$s->product->id],
        ]), $admin);
        $this->assertSame([$s->vendor->id], $c->vendors()->pluck('vendors.id')->all());
        $this->assertSame([$s->marketer->id], $c->marketers()->pluck('marketers.id')->all());
        $this->assertSame([$s->product->id], $c->products()->pluck('products.id')->all());

        $svc->update($c, $this->baseData(['code' => $c->code, 'vendor_ids' => [], 'marketer_ids' => [], 'product_ids' => []]));
        $this->assertSame(0, $c->vendors()->count() + $c->marketers()->count() + $c->products()->count());
    }

    public function test_untargeted_coupon_applies_to_vendor_and_marketer_lines(): void
    {
        $s = MarketplaceScenario::make()->build();
        $c = app(CouponService::class)->create($this->baseData(), Admin::factory()->create());

        $this->assertNull($this->apply($c, $s, $this->cartLine($s))['error']);
        $this->assertNull($this->apply($c, $s, $this->cartLine($s, true))['error']);
    }

    public function test_vendor_targeted_coupon(): void
    {
        $s = MarketplaceScenario::make()->build();
        $svc = app(CouponService::class);
        $admin = Admin::factory()->create();

        $hit = $svc->create($this->baseData(['vendor_ids' => [$s->vendor->id]]), $admin);
        $this->assertGreaterThan(0, $this->apply($hit, $s, $this->cartLine($s))['discount']);

        $other = \App\Models\Vendor::factory()->create();
        $miss = $svc->create($this->baseData(['vendor_ids' => [$other->id]]), $admin);
        $this->assertSame(0, $this->apply($miss, $s, $this->cartLine($s))['discount']);
    }

    public function test_marketer_targeted_coupon_matches_attributed_marketer_only(): void
    {
        $s = MarketplaceScenario::make()->build();
        $svc = app(CouponService::class);
        $admin = Admin::factory()->create();

        $hit = $svc->create($this->baseData(['marketer_ids' => [$s->marketer->id]]), $admin);
        $this->assertGreaterThan(0, $this->apply($hit, $s, $this->cartLine($s, true))['discount']);
        // A plain vendor line has no marketer, so a marketer-targeted coupon must not apply.
        $this->assertSame(0, $this->apply($hit, $s, $this->cartLine($s))['discount']);
    }

    public function test_product_pivot_is_enforced_for_admin_coupons(): void
    {
        $s = MarketplaceScenario::make()->build();
        $svc = app(CouponService::class);
        $admin = Admin::factory()->create();

        $hit = $svc->create($this->baseData(['product_ids' => [$s->product->id]]), $admin);
        $this->assertGreaterThan(0, $this->apply($hit, $s, $this->cartLine($s))['discount']);

        $otherProduct = \App\Models\Product::factory()->create();
        $miss = $svc->create($this->baseData(['product_ids' => [$otherProduct->id]]), $admin);
        $this->assertSame(0, $this->apply($miss, $s, $this->cartLine($s))['discount']);
    }

    public function test_product_picker_filters_by_vendor_and_marketer(): void
    {
        $s = MarketplaceScenario::make()->build();
        $stranger = \App\Models\Product::factory()->create(['name_en' => $s->product->name_en.' stranger']);
        Permission::firstOrCreate(['name' => 'coupons.view', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('coupons.view');
        $q = substr((string) $s->product->name_en, 0, 4);

        $ids = fn (array $p) => collect($this->actingAs($admin, 'admin')
            ->getJson(route('admin.coupons.search-products', ['q' => $q] + $p))
            ->assertOk()->json('results'))->pluck('id')->all();

        $this->assertContains($stranger->id, $ids([]));
        $byVendor = $ids(['vendor_ids' => [$s->vendor->id]]);
        $this->assertContains($s->product->id, $byVendor);
        $this->assertNotContains($stranger->id, $byVendor);
        $byMarketer = $ids(['marketer_ids' => [$s->marketer->id]]);
        $this->assertContains($s->marketerListing->productVariant->product_id, $byMarketer);
        $this->assertNotContains($stranger->id, $byMarketer);
    }
}
