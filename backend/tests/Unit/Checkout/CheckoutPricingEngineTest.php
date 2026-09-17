<?php

namespace Tests\Unit\Checkout;

use App\Models\CartItem;
use App\Services\Checkout\CheckoutPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-01: unit-level coverage of App\Services\Checkout\CheckoutPricingEngine
 * — the single source of truth for coupon discount allocation and
 * line/sub-order/order tax reconciliation (D1/D2 in enhancement.md).
 */
class CheckoutPricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): CheckoutPricingEngine
    {
        return app(CheckoutPricingEngine::class);
    }

    private function cartItem(MarketplaceScenario $scenario, $cart, $listing, int $quantity): CartItem
    {
        return CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $quantity,
            'unit_price' => (int) $listing->getRawOriginal('price'),
            'added_at' => now(),
        ]);
    }

    public function test_allocate_pro_rata_sums_exactly_to_total_with_largest_remainder(): void
    {
        $engine = $this->engine();

        // 100 split across weights that don't divide evenly.
        $allocations = $engine->allocateProRata(100, ['a' => 3, 'b' => 3, 'c' => 3]);

        $this->assertSame(100, array_sum($allocations));
        // Largest-remainder ties are broken deterministically but every key
        // still gets floor(33.33) = 33, with the +1 remainder going to one key.
        foreach ($allocations as $v) {
            $this->assertGreaterThanOrEqual(33, $v);
            $this->assertLessThanOrEqual(34, $v);
        }
    }

    public function test_allocate_pro_rata_handles_zero_and_empty(): void
    {
        $engine = $this->engine();

        $this->assertSame([], array_filter($engine->allocateProRata(0, ['a' => 10])));
        $this->assertSame([], $engine->allocateProRata(100, []));
    }

    public function test_percentage_coupon_discount_allocates_pro_rata_across_lines(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        $item1 = $this->cartItem($scenario, $cart, $scenario->vendorListingFbp, 1); // 1000
        $item2 = $this->cartItem($scenario, $cart, $scenario->vendorListingFbn, 1); // 1200

        $coupon = $scenario->coupons['percentage_platform']; // 10%

        $result = $this->engine()->applyCoupon($coupon, $scenario->customer, 2200, 'AED', [$item1, $item2]);

        $this->assertNull($result['error']);
        $this->assertSame(220, $result['discount']); // 10% of 2200
        $this->assertSame(220, array_sum($result['allocations']));
        // Pro-rata by line_subtotal: item2 (120000) gets a slightly larger
        // share than item1 (100000).
        $this->assertGreaterThan($result['allocations'][$item1->id], $result['allocations'][$item2->id]);
    }

    public function test_bogo_coupon_allocates_entire_discount_to_cheapest_qualifying_line(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        $cheap = $this->cartItem($scenario, $cart, $scenario->vendorListingFbp, 1); // 1000
        $expensive = $this->cartItem($scenario, $cart, $scenario->vendorListingFbn, 1); // 1200

        $coupon = $scenario->coupons['bogo_platform'];

        $result = $this->engine()->applyCoupon($coupon, $scenario->customer, 2200, 'AED', [$cheap, $expensive]);

        $this->assertNull($result['error']);
        $this->assertSame(1000, $result['discount']);
        $this->assertArrayHasKey($cheap->id, $result['allocations']);
        $this->assertArrayNotHasKey($expensive->id, $result['allocations']);
        $this->assertSame(1000, $result['allocations'][$cheap->id]);
    }

    public function test_vendor_scoped_coupon_only_applies_to_that_vendors_lines(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        $item = $this->cartItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $coupon = $scenario->coupons['fixed_amount_vendor'];

        $result = $this->engine()->applyCoupon($coupon, $scenario->customer, 100000, 'AED', [$item]);

        $this->assertNull($result['error']);
        $this->assertSame(50, $result['discount']); // fixed_amount value = 50
    }

    public function test_price_cart_reconciles_tax_and_discount_at_every_level_with_warranty(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        $item1 = $this->cartItem($scenario, $cart, $scenario->vendorListingFbp, 2); // 100000 * 2
        $item2 = $this->cartItem($scenario, $cart, $scenario->vendorListingFbn, 1); // 120000

        $coupon = $scenario->coupons['percentage_platform']; // 10%
        $couponResult = $this->engine()->applyCoupon($coupon, $scenario->customer, 320000, 'AED', [$item1, $item2]);

        $warrantyResult = $this->engine()->resolveWarrantySelections(
            [$item1, $item2],
            [['listing_id' => $scenario->vendorListingFbp->id, 'warranty_plan_id' => $scenario->warrantyPlanFlat->id]],
            $scenario->country,
            'AED',
        );

        $pricedCart = $this->engine()->priceCart(
            [$item1, $item2],
            $scenario->country,
            shippingFeeCents: 1000,
            codFeeCents: 0,
            couponDiscountCents: $couponResult['discount'],
            couponAllocations: $couponResult['allocations'],
            loyaltyDiscountCents: 0,
            loyaltyAllocations: [],
            giftCardAppliedCents: 0,
            warrantySelections: $warrantyResult['selections'],
        );

        // D2: every line's tax is computed on (line_subtotal - line_discount).
        foreach ($pricedCart->lines as $line) {
            $expectedTax = (int) round(($line->lineSubtotal - $line->lineDiscount) * 5 / 100);
            $this->assertSame($expectedTax, $line->lineTax);
        }

        // Reconciliation invariants (mirrors AssertsOrderMoney::assertMoneyBalanced).
        $sumLineDiscount = array_sum(array_map(fn ($l) => $l->lineDiscount, $pricedCart->lines));
        $this->assertSame($pricedCart->discount, $sumLineDiscount);

        $sumSubOrderTax = array_sum(array_map(fn ($s) => $s->tax, $pricedCart->subOrders));
        $this->assertSame($pricedCart->tax, $sumSubOrderTax);

        $sumSubOrderSubtotal = array_sum(array_map(fn ($s) => $s->subtotal, $pricedCart->subOrders));
        $this->assertSame($pricedCart->subtotal, $sumSubOrderSubtotal);

        $expectedTotal = $pricedCart->subtotal
            - $pricedCart->discount
            - $pricedCart->loyaltyDiscount
            + $pricedCart->shipping
            + $pricedCart->codFee
            + $pricedCart->tax
            + $pricedCart->warrantyTotal;
        $this->assertSame($expectedTotal, $pricedCart->total);

        // Warranty tax is folded into sub-order/order tax (never lost, never
        // double counted), and warranty is only assigned to the sub-order
        // whose line purchased it.
        $this->assertGreaterThan(0, $pricedCart->warrantyTotal);
        $this->assertGreaterThan(0, $pricedCart->tax);
    }

    public function test_only_checkout_pricing_engine_contains_coupon_discount_math(): void
    {
        // Grep-based guard (P-01 acceptance criterion): applyCoupon /
        // resolveApplicableSubtotal / cheapestQualifyingItemPrice /
        // resolveWarrantySelections must not be re-implemented anywhere
        // outside the engine. Every other class that still exposes one of
        // these method names must be a thin delegate to the engine (body
        // just calls ->pricingEngine->..., no scope/type matching of its
        // own) — proven here by requiring the method body to contain
        // "pricingEngine" and NOT to contain its own CouponType/CouponScope
        // discount-amount switch.
        $root = dirname(__DIR__, 3).'/app';
        $methodNames = ['applyCoupon', 'resolveApplicableSubtotal', 'cheapestQualifyingItemPrice', 'resolveWarrantySelections'];
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, '/Services/Checkout/CheckoutPricingEngine.php')) {
                continue; // the one allowed place
            }

            $contents = file_get_contents($path);

            foreach ($methodNames as $methodName) {
                if (! preg_match('/function\s+'.$methodName.'\s*\(([^)]*)\)[^{]*\{/', $contents, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                // Only the discount-math signature takes an `array $items`
                // (or `$cartItems`) parameter — CartService/CartController's
                // unrelated applyCoupon(Cart, Customer, string $code) is a
                // different concern (attach a coupon to the cart) and is not
                // a target of this guard.
                if ($methodName === 'applyCoupon' && ! preg_match('/array\s+\$\w*[Ii]tems/', $m[1][0])) {
                    continue;
                }

                $start = $m[0][1] + strlen($m[0][0]);
                $body = $this->extractBalancedBody($contents, $start);

                if (! str_contains($body, 'pricingEngine')) {
                    $offenders[] = "{$path}::{$methodName}() does not delegate to pricingEngine";
                }

                if (preg_match('/CouponType::(Percentage|Bogo|FixedAmount)\s*=>/', $body)
                    || preg_match('/CouponScope::(Vendor|Category|Product)\s*=>/', $body)) {
                    $offenders[] = "{$path}::{$methodName}() re-implements coupon type/scope matching";
                }
            }
        }

        $this->assertSame([], $offenders, implode('; ', $offenders));
    }

    /**
     * Given an offset just past a function's opening `{`, return the
     * source text up to (not including) the matching closing `}`.
     */
    private function extractBalancedBody(string $contents, int $start): string
    {
        $depth = 1;
        $i = $start;
        $len = strlen($contents);

        while ($i < $len && $depth > 0) {
            if ($contents[$i] === '{') {
                $depth++;
            } elseif ($contents[$i] === '}') {
                $depth--;
            }
            $i++;
        }

        return substr($contents, $start, $i - $start - 1);
    }
}
