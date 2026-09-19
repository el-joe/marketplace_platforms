<?php

namespace Tests\Feature\Checkout;

use App\Enums\CouponShippingTypeRestriction as R;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Checkout\CouponEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * F10. Documented behaviour: a restricted coupon REJECTS the whole cart when
 * ANY line has a different shipping type (no partial discount on mixed carts).
 * Types: admin listings + fbn => fbn; cross_dock => fbp; anything else => fbm.
 */
class CouponShippingTypeRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private function item(MarketplaceScenario $s, $listing): CartItem
    {
        $cart = Cart::firstOrCreate(['user_id' => $s->customer->id], [
            'country_id' => $s->country->id, 'currency' => 'AED',
            'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0,
        ]);

        return CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $listing->id, 'quantity' => 1,
            'unit_price' => (int) $listing->getRawOriginal('price'), 'added_at' => now(),
        ]);
    }

    private function eval(MarketplaceScenario $s, R $r, array $items): array
    {
        $coupon = $s->coupons['percentage_platform'];
        $coupon->update(['shipping_type_restriction' => $r->value]);
        $sub = array_sum(array_map(fn ($i) => (int) $i->unit_price, $items));

        return app(CouponEligibilityService::class)->evaluate($coupon->fresh(), $s->customer, $sub, 'AED', $items);
    }

    public function test_matrix_and_mixed_cart(): void
    {
        $s = MarketplaceScenario::make()->build();
        $fbm = $this->item($s, $s->vendorListingFbp);   // fulfillment_model fbm
        $fbn = $this->item($s, $s->vendorListingFbn);

        $this->assertNull($this->eval($s, R::All, [$fbm])['error']);
        $this->assertNull($this->eval($s, R::All, [$fbm, $fbn])['error']);
        $this->assertNull($this->eval($s, R::Fbm, [$fbm])['error']);
        $this->assertNull($this->eval($s, R::Fbn, [$fbn])['error']);
        $this->assertNotNull($this->eval($s, R::Fbn, [$fbm])['error']);
        $this->assertNotNull($this->eval($s, R::Fbp, [$fbm])['error']);
        $this->assertNotNull($this->eval($s, R::Fbm, [$fbn])['error']);
        // mixed cart: rejected outright
        $this->assertNotNull($this->eval($s, R::Fbn, [$fbn, $fbm])['error']);
        $this->assertNotNull($this->eval($s, R::Fbm, [$fbn, $fbm])['error']);
    }

    public function test_error_strings_exist_in_both_locales(): void
    {
        foreach (['en', 'ar'] as $l) {
            $m = __('common.exceptions.checkout.coupon.shipping_type_restricted', ['type' => 'FBN'], $l);
            $this->assertStringNotContainsString('shipping_type_restricted', $m);
            $this->assertSame($l === 'en'
                ? 'This coupon is not valid for the selected shipping type.'
                : 'هذه القسيمة غير صالحة لنوع الشحن المحدد', $m);
        }
    }

    public function test_request_rules_validate_enum(): void
    {
        foreach ([
            \App\Http\Requests\Admin\StoreCouponRequest::class,
            \App\Http\Requests\Admin\UpdateCouponRequest::class,
            \App\Http\Requests\Vendor\StoreCouponRequest::class,
            \App\Http\Requests\Vendor\UpdateCouponRequest::class,
        ] as $cls) {
            $rule = (new $cls)->rules()['shipping_type_restriction'];
            $this->assertTrue(Validator::make(['x' => 'fbn'], ['x' => $rule])->passes(), $cls);
            $this->assertTrue(Validator::make(['x' => 'all'], ['x' => $rule])->passes(), $cls);
            $this->assertTrue(Validator::make(['x' => 'bogus'], ['x' => $rule])->fails(), $cls);
        }
    }
}
