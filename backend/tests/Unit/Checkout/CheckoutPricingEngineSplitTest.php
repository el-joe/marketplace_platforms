<?php

namespace Tests\Unit\Checkout;

use App\Models\CartItem;
use App\Services\Checkout\CheckoutPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-03: unit-level verification of
 * CheckoutPricingEngine::computeMoneySplit()'s reconciliation identity,
 * isolated from the full HTTP checkout flow (see
 * tests/Feature/Checkout/CheckoutMoneySplitTest.php for the end-to-end
 * version through place-order).
 */
class CheckoutPricingEngineSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_reconciles_to_the_unit_single_vendor_card_no_coupon(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $engine = app(CheckoutPricingEngine::class);

        $cart = \App\Models\Cart::create(['user_id' => $scenario->customer->id, 'country_id' => $scenario->country->id, 'currency' => 'AED', 'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 2,
            'unit_price' => (int) $scenario->vendorListingFbp->price,
            'added_at' => now(),
        ]);

        $items = [$item];
        $pricedCart = $engine->priceCart(
            $items, $scenario->country, 0, 0, 0, [], 0, [], 0, [],
        );

        $gatewayCfg = $scenario->countryPaymentGateways['stripe'];

        $split = $engine->computeMoneySplit(
            items: $items,
            country: $scenario->country,
            coupon: null,
            couponAllocations: [],
            vendorContributionByGroup: [],
            adminSubsidyByGroup: [],
            carrierRawFeeByGroup: [],
            chargedShippingByGroup: [],
            gatewayFeePct: (float) $gatewayCfg->fee_pct,
            gatewayFeeFixed: (int) $gatewayCfg->fee_fixed,
            amountDueGatewayCents: $pricedCart->total,
            isCod: false,
            codFeeCents: 0,
            warrantyTotalCents: 0,
        );

        $sumVendorPayout = array_sum(array_column($split['sub_orders'], 'vendor_payout'));
        $amountPaid = $pricedCart->total;

        $this->assertSame(
            $amountPaid,
            $sumVendorPayout + $split['platform_net'] + $pricedCart->tax + $split['marketer_commission_total'] + $split['gateway_fee_total'] + $split['carrier_cost_covered_total'],
        );
    }

    public function test_three_vendor_card_order_charges_fee_fixed_once(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $engine = app(CheckoutPricingEngine::class);

        $vendor2 = \App\Models\Vendor::create([
            'name' => 'Vendor 2', 'email' => 'v2-'.\Illuminate\Support\Str::random(6).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'), 'password' => bcrypt('x'),
            'store_name' => 'Store 2', 'store_slug' => 'store-2-'.\Illuminate\Support\Str::random(6),
            'business_type' => 'llc', 'payout_schedule' => 'monthly', 'global_status' => 'active',
            'country_id' => $scenario->country->id, 'approved_at' => now(), 'warranty_months' => 12,
        ]);
        $warehouse2 = \App\Models\Warehouse::create([
            'country_id' => $scenario->country->id, 'name' => 'W2', 'code' => 'W2-'.\Illuminate\Support\Str::random(6),
            'type' => 'seller_owned', 'owner_vendor_id' => $vendor2->id, 'is_active' => true,
        ]);
        $listing2 = \App\Models\VendorListing::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'vendor_id' => $vendor2->id,
            'product_variant_id' => $scenario->variants[1]->id, 'country_id' => $scenario->country->id,
            'warehouse_id' => $warehouse2->id, 'price' => 500, 'currency' => 'AED',
            'condition' => 'new', 'fulfillment_model' => 'fbm', 'status' => 'active',
        ]);

        $vendor3 = \App\Models\Vendor::create([
            'name' => 'Vendor 3', 'email' => 'v3-'.\Illuminate\Support\Str::random(6).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'), 'password' => bcrypt('x'),
            'store_name' => 'Store 3', 'store_slug' => 'store-3-'.\Illuminate\Support\Str::random(6),
            'business_type' => 'llc', 'payout_schedule' => 'monthly', 'global_status' => 'active',
            'country_id' => $scenario->country->id, 'approved_at' => now(), 'warranty_months' => 12,
        ]);
        $warehouse3 = \App\Models\Warehouse::create([
            'country_id' => $scenario->country->id, 'name' => 'W3', 'code' => 'W3-'.\Illuminate\Support\Str::random(6),
            'type' => 'seller_owned', 'owner_vendor_id' => $vendor3->id, 'is_active' => true,
        ]);
        $variant3 = \App\Models\ProductVariant::create([
            'product_id' => $scenario->product->id, 'sku' => 'SKU-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
            'variant_name' => 'Variant 3', 'is_default' => false, 'is_active' => true, 'position' => 2,
        ]);
        $listing3 = \App\Models\VendorListing::create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'vendor_id' => $vendor3->id,
            'product_variant_id' => $variant3->id, 'country_id' => $scenario->country->id,
            'warehouse_id' => $warehouse3->id, 'price' => 700, 'currency' => 'AED',
            'condition' => 'new', 'fulfillment_model' => 'fbm', 'status' => 'active',
        ]);

        $cart = \App\Models\Cart::create(['user_id' => $scenario->customer->id, 'country_id' => $scenario->country->id, 'currency' => 'AED', 'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0]);
        $items = [
            CartItem::create(['cart_id' => $cart->id, 'vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1, 'unit_price' => (int) $scenario->vendorListingFbp->price, 'added_at' => now()]),
            CartItem::create(['cart_id' => $cart->id, 'vendor_listing_id' => $listing2->id, 'quantity' => 1, 'unit_price' => (int) $listing2->price, 'added_at' => now()]),
            CartItem::create(['cart_id' => $cart->id, 'vendor_listing_id' => $listing3->id, 'quantity' => 1, 'unit_price' => (int) $listing3->price, 'added_at' => now()]),
        ];

        $pricedCart = $engine->priceCart($items, $scenario->country, 0, 0, 0, [], 0, [], 0, []);
        $gatewayCfg = $scenario->countryPaymentGateways['stripe'];

        $split = $engine->computeMoneySplit(
            items: $items, country: $scenario->country, coupon: null, couponAllocations: [],
            vendorContributionByGroup: [], adminSubsidyByGroup: [], carrierRawFeeByGroup: [], chargedShippingByGroup: [],
            gatewayFeePct: (float) $gatewayCfg->fee_pct, gatewayFeeFixed: (int) $gatewayCfg->fee_fixed,
            amountDueGatewayCents: $pricedCart->total, isCod: false, codFeeCents: 0, warrantyTotalCents: 0,
        );

        $this->assertCount(3, $split['sub_orders']);
        $this->assertSame((int) $gatewayCfg->fee_fixed, $split['gateway_fee_total'] - (int) floor($pricedCart->total * (float) $gatewayCfg->fee_pct / 100));

        $sumVendorPayout = array_sum(array_column($split['sub_orders'], 'vendor_payout'));
        $this->assertSame(
            $pricedCart->total,
            $sumVendorPayout + $split['platform_net'] + $pricedCart->tax + $split['marketer_commission_total'] + $split['gateway_fee_total'] + $split['carrier_cost_covered_total'],
        );
    }

    public function test_vendor_funded_coupon_reduces_only_that_vendor_payout(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $engine = app(CheckoutPricingEngine::class);

        $cart = \App\Models\Cart::create(['user_id' => $scenario->customer->id, 'country_id' => $scenario->country->id, 'currency' => 'AED', 'subtotal' => 0, 'discount' => 0, 'estimated_shipping' => 0, 'estimated_tax' => 0, 'estimated_total' => 0]);
        $item = CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1, 'unit_price' => (int) $scenario->vendorListingFbp->price, 'added_at' => now(),
        ]);
        $items = [$item];

        $coupon = $scenario->coupons['percentage_vendor'];
        $couponResult = $engine->applyCoupon($coupon, $scenario->customer, (int) $scenario->vendorListingFbp->price, 'AED', $items);
        $this->assertNull($couponResult['error']);

        $pricedCart = $engine->priceCart(
            $items, $scenario->country, 0, 0, $couponResult['discount'], $couponResult['allocations'], 0, [], 0, [],
        );

        $split = $engine->computeMoneySplit(
            items: $items, country: $scenario->country, coupon: $coupon, couponAllocations: $couponResult['allocations'],
            vendorContributionByGroup: [], adminSubsidyByGroup: [], carrierRawFeeByGroup: [], chargedShippingByGroup: [],
            gatewayFeePct: 0, gatewayFeeFixed: 0, amountDueGatewayCents: 0, isCod: true, codFeeCents: 0, warrantyTotalCents: 0,
        );

        $vendorGroup = $split['sub_orders'][$scenario->vendor->id];
        $this->assertSame($couponResult['discount'], $vendorGroup['vendor_coupon_cost']);
        $this->assertSame(0, $vendorGroup['platform_coupon_cost']);

        $noCouponSplit = $engine->computeMoneySplit(
            items: $items, country: $scenario->country, coupon: null, couponAllocations: [],
            vendorContributionByGroup: [], adminSubsidyByGroup: [], carrierRawFeeByGroup: [], chargedShippingByGroup: [],
            gatewayFeePct: 0, gatewayFeeFixed: 0, amountDueGatewayCents: 0, isCod: true, codFeeCents: 0, warrantyTotalCents: 0,
        );

        $this->assertSame(
            $couponResult['discount'],
            $noCouponSplit['sub_orders'][$scenario->vendor->id]['vendor_payout'] - $vendorGroup['vendor_payout'],
        );
    }
}
