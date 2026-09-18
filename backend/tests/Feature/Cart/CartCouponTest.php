<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Coupon-applied-but-discount-shows-0 bug (docs/plans/cart-warranty-receiver-coupon-fixes.md
 * Issue 3): CartService::applyCoupon() and CartService::recalculateCart() must
 * use the same eligibility validation (CheckoutPricingEngine, via
 * CheckoutCalculationService), so a coupon that is accepted at apply-time can
 * never later silently compute a 0 discount while still looking "applied".
 */
class CartCouponTest extends TestCase
{
    use RefreshDatabase;

    private function cartService(): CartService
    {
        return app(CartService::class);
    }

    private function authedCartWithItem(MarketplaceScenario $scenario, int $qty = 1): Cart
    {
        $cart = Cart::create([
            'user_id' => $scenario->customer->id,
            'country_id' => $scenario->country->id,
            'currency' => 'AED',
            'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => $qty,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        return $cart->fresh();
    }

    private function guestCartWithItem(MarketplaceScenario $scenario, int $qty = 1): Cart
    {
        $cart = Cart::create([
            'session_token' => (string) Str::uuid(),
            'user_id' => null,
            'country_id' => $scenario->country->id,
            'currency' => 'AED',
            'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0,
            'expires_at' => now()->addDays(30),
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => $qty,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        return $cart->fresh();
    }

    public function test_authed_customer_applying_a_valid_eligible_coupon_gets_a_nonzero_discount(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = $this->authedCartWithItem($scenario);
        $coupon = $scenario->coupons['percentage_platform']; // 10%, all-eligible, active

        $applied = $this->cartService()->applyCoupon($cart, $scenario->customer, $coupon->code);

        $this->assertSame($coupon->id, $applied->id);

        $cart = $cart->fresh();
        $expectedDiscount = (int) round($cart->subtotal * 0.10);
        $this->assertSame($coupon->id, $cart->coupon_id);
        $this->assertGreaterThan(0, $cart->discount);
        $this->assertSame($expectedDiscount, $cart->discount);
    }

    public function test_guest_cart_coupon_is_cleanly_rejected_not_silently_zeroed(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = $this->guestCartWithItem($scenario);
        $coupon = $scenario->coupons['percentage_platform'];

        $this->expectException(\DomainException::class);

        try {
            $this->cartService()->applyCoupon($cart, null, $coupon->code);
        } finally {
            // The coupon must never get attached to a guest cart as a
            // side effect of a failed/rejected apply attempt.
            $this->assertNull($cart->fresh()->coupon_id);
        }
    }

    public function test_coupon_failing_engine_only_eligibility_is_rejected_at_apply_time_not_later(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = $this->authedCartWithItem($scenario);
        $coupon = $scenario->coupons['percentage_platform'];
        // currency_mismatch is only checked by CheckoutPricingEngine::
        // validateCouponEligibility(), never by the old narrower
        // CartService::applyCoupon() checks — this coupon must now be
        // rejected immediately, not accepted and zeroed on the next
        // recalculation.
        $coupon->update(['currency' => 'USD']);

        try {
            $this->cartService()->applyCoupon($cart, $scenario->customer, $coupon->code);
            $this->fail('Expected a DomainException for a currency-mismatched coupon.');
        } catch (\DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $cart = $cart->fresh();
        $this->assertNull($cart->coupon_id);
        $this->assertSame(0, $cart->discount);
    }

    public function test_recalculation_detaches_coupon_and_reports_error_when_min_order_no_longer_met(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = $this->authedCartWithItem($scenario, qty: 2);
        $coupon = $scenario->coupons['percentage_platform'];

        $this->cartService()->applyCoupon($cart, $scenario->customer, $coupon->code);
        $cart = $cart->fresh();
        $this->assertSame($coupon->id, $cart->coupon_id);
        $this->assertGreaterThan(0, $cart->discount);

        // Make the coupon no longer eligible for this cart's (now unchanged)
        // subtotal by raising min_order_amount above it, then trigger a
        // recalculation the way any subsequent cart mutation would
        // (removeItem -> recalculateCart()).
        $coupon->update(['min_order_amount' => $cart->subtotal + 1]);

        $item = $cart->items()->first();
        $this->cartService()->updateItem($cart, $item->id, $item->quantity, null, false, $scenario->country->id);

        $cart = $cart->fresh();
        $cart->load('coupon');

        $this->assertNull($cart->coupon_id);
        $this->assertNull($cart->coupon);
        $this->assertSame(0, $cart->discount);
    }
}
