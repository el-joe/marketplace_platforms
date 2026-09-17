<?php

namespace Tests\Unit\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Services\Checkout\CheckoutPricingEngine;
use App\Services\Checkout\CouponEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-04: unit coverage for the rules
 * CouponEligibilityService::evaluate() enforces (backed by
 * CheckoutPricingEngine::applyCoupon()) — active window, country, min
 * order, customer eligibility (new_customers/specific_users/
 * specific_segment), per-customer limit, per-month limit, total limit,
 * vendor scope, category scope (via coupon_products/category), stackability,
 * and free_shipping actually zeroing shipping (seller-party resolution).
 */
class CouponEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CouponEligibilityService
    {
        return app(CouponEligibilityService::class);
    }

    private function dummyOrder(MarketplaceScenario $scenario): Order
    {
        static $n = 0;
        $n++;

        return Order::create([
            'order_number' => 'ORD-TEST-DUMMY-' . $n,
            'customer_id' => $scenario->customer->id,
            'country_id' => $scenario->country->id,
            'status' => 'placed',
            'currency' => 'AED',
            'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'tax' => 0, 'cod_fee' => 0,
            'warranty_total' => 0, 'total' => 100,
            'payment_method' => 'cod', 'payment_status' => 'pending',
            'placed_at' => now(),
            'shipping_address_snapshot' => [],
            'ip_address' => '127.0.0.1',
        ]);
    }

    private function cartWithItem(MarketplaceScenario $scenario, $listing, int $qty = 1): array
    {
        $cart = Cart::create([
            'user_id' => $scenario->customer->id,
            'country_id' => $scenario->country->id,
            'currency' => 'AED',
            'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0,
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $qty,
            'unit_price' => (int) $listing->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        return [$cart, $item];
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['is_active' => false]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_expired_window_is_rejected(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['valid_until' => now()->subDay()]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_country_restriction_rejects_customer_outside_allowed_countries(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['country_ids' => ['some-other-country-id']]);

        $result = $this->service()->evaluate(
            $coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item], $scenario->country->id
        );

        $this->assertNotNull($result['error']);
    }

    public function test_country_restriction_allows_customer_inside_allowed_countries(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['country_ids' => [$scenario->country->id]]);

        $result = $this->service()->evaluate(
            $coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item], $scenario->country->id
        );

        $this->assertNull($result['error']);
    }

    public function test_min_order_amount_rejects_small_orders(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['min_order_amount' => (int) $item->unit_price * 100]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_new_customers_only_rejects_customer_with_a_completed_order(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['customer_eligibility' => 'new_customers']);

        Order::create([
            'order_number' => 'ORD-TEST-1',
            'customer_id' => $scenario->customer->id,
            'country_id' => $scenario->country->id,
            'status' => 'completed',
            'currency' => 'AED',
            'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'tax' => 0, 'cod_fee' => 0,
            'warranty_total' => 0, 'total' => 100,
            'payment_method' => 'cod', 'payment_status' => 'captured',
            'placed_at' => now(),
            'shipping_address_snapshot' => [],
            'ip_address' => '127.0.0.1',
        ]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_new_customers_only_allows_customer_with_no_completed_order(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['customer_eligibility' => 'new_customers']);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
    }

    public function test_specific_users_rejects_customer_not_in_the_list(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['customer_eligibility' => 'specific_users', 'eligible_customer_ids' => ['someone-else']]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_specific_users_allows_customer_in_the_list(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['customer_eligibility' => 'specific_users', 'eligible_customer_ids' => [$scenario->customer->id]]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
    }

    public function test_specific_segment_rejects_customer_not_in_the_member_list(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['customer_eligibility' => 'specific_segment', 'eligible_customer_ids' => ['someone-else']]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_per_customer_usage_limit_rejects_once_reached(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['usage_limit_per_customer' => 1]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $scenario->customer->id,
            'order_id' => $this->dummyOrder($scenario)->id,
            'discount_amount' => 10,
            'used_at' => now(),
            'status' => 'consumed',
        ]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_released_usage_does_not_count_against_per_customer_limit(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['usage_limit_per_customer' => 1]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $scenario->customer->id,
            'order_id' => $this->dummyOrder($scenario)->id,
            'discount_amount' => 10,
            'used_at' => now(),
            'status' => 'released',
        ]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
    }

    public function test_per_month_usage_limit_rejects_once_reached(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['usage_limit_per_customer' => 100, 'max_orders_per_customer_per_month' => 1]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $scenario->customer->id,
            'order_id' => $this->dummyOrder($scenario)->id,
            'discount_amount' => 10,
            'used_at' => now(),
            'status' => 'consumed',
        ]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_total_usage_limit_rejects_once_reached(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['usage_limit_total' => 1, 'times_used' => 1]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_vendor_scope_rejects_items_from_a_different_vendor(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_vendor'];
        $otherVendor = \App\Models\Vendor::factory()->create();
        $coupon->update(['vendor_id' => $otherVendor->id]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_vendor_scope_allows_items_from_the_matching_vendor(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_vendor'];
        $coupon->update(['vendor_id' => $scenario->vendor->id]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
        $this->assertGreaterThan(0, $result['discount']);
    }

    public function test_category_scope_via_matching_category(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['scope' => 'category', 'category_id' => $scenario->category->id]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
        $this->assertGreaterThan(0, $result['discount']);
    }

    public function test_category_scope_rejects_a_different_category(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $otherCategory = \App\Models\Category::factory()->create();
        $coupon->update(['scope' => 'category', 'category_id' => $otherCategory->id]);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNotNull($result['error']);
    }

    public function test_product_scope_via_coupon_products(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_vendor'];
        $coupon->update(['scope' => 'product', 'vendor_id' => $scenario->vendor->id]);
        $coupon->products()->attach($scenario->product->id);

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
        $this->assertGreaterThan(0, $result['discount']);
    }

    public function test_stackable_false_rejects_when_stacked_with_another_discount(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['is_stackable' => false]);

        $result = $this->service()->evaluate(
            $coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item], null, true
        );

        $this->assertNotNull($result['error']);
    }

    public function test_stackable_true_allows_stacking(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['is_stackable' => true]);

        $result = $this->service()->evaluate(
            $coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item], null, true
        );

        $this->assertNull($result['error']);
    }

    public function test_free_shipping_resolves_seller_parties_in_scope(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        [$cart, $item] = $this->cartWithItem($scenario, $scenario->vendorListingFbp);
        $coupon = $scenario->coupons['free_shipping_platform'];

        $result = $this->service()->evaluate($coupon, $scenario->customer, (int) $item->unit_price, 'AED', [$item]);

        $this->assertNull($result['error']);
        $this->assertSame(0, $result['discount']);
        $this->assertNotEmpty($result['free_shipping_seller_parties']);
        $this->assertContains($scenario->vendor->id, $result['free_shipping_seller_parties']);
    }

    public function test_reserve_then_release_leaves_times_used_unchanged(): void
    {
        // enhancement.md P-04 acceptance criterion: a declined card payment
        // (or any other pre-capture rollback) must leave times_used exactly
        // where it started. Checkout calls reserve() before the gateway
        // call and releaseForOrder() from the decline/exception handler
        // (Customer\CheckoutController); this exercises that same pair.
        $scenario = MarketplaceScenario::make()->build();
        $coupon = $scenario->coupons['percentage_platform'];
        $coupon->update(['usage_limit_total' => 5, 'times_used' => 0]);
        $order = $this->dummyOrder($scenario);

        $usageService = app(\App\Services\Checkout\CouponUsageService::class);
        $usageService->reserve($coupon->id, $scenario->customer, $order, 10);

        $this->assertSame(1, $coupon->refresh()->times_used);

        $usageService->releaseForOrder($order);

        $this->assertSame(0, $coupon->refresh()->times_used);
        $this->assertSame(
            CouponUsage::STATUS_RELEASED,
            CouponUsage::where('order_id', $order->id)->first()->status
        );
    }
}
