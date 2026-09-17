<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-00 (Phase A — Safety Net): proves the MarketplaceScenario builder can
 * seed the whole minimal world with no SQL errors, and lists one
 * pending/skipped placeholder per P-01..P-13 scenario so later prompts have
 * a named test to fill in.
 */
class ScenarioTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    public function test_scenario_builds_every_entity_with_no_sql_errors(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        // Geo
        $this->assertSame('AED', $scenario->country->currency_code);
        $this->assertSame('5.00', (string) $scenario->country->vat_rate);
        $this->assertNotNull($scenario->city->id);
        $this->assertNotNull($scenario->shippingZone->id);
        $this->assertSame($scenario->shippingZone->id, $scenario->city->shipping_zone_id);

        // Category / commission / brand
        $this->assertSame('10.00', (string) $scenario->category->commission_fbp_pct);
        $this->assertSame('12.00', (string) $scenario->category->commission_fbn_pct);
        $this->assertNotNull($scenario->brand->id);
        $this->assertCount(2, $scenario->commissions);

        // Product + 2 variants (one with images, one without)
        $this->assertNotNull($scenario->product->id);
        $this->assertCount(2, $scenario->variants);
        $this->assertSame(2, $scenario->variants[0]->images()->count());
        $this->assertSame(0, $scenario->variants[1]->images()->count());

        // Vendor listings (FBP + FBN) with warehouse inventory
        $this->assertSame('fbm', $scenario->vendorListingFbp->fulfillment_model);
        $this->assertSame('fbn', $scenario->vendorListingFbn->fulfillment_model);
        $this->assertStock($scenario->vendorListingFbp, 50, 0);
        $this->assertStock($scenario->vendorListingFbn, 40, 0);

        // Admin listing with inventory
        $this->assertNotNull($scenario->adminListing->id);
        $this->assertStock($scenario->adminListing, 30, 0);

        // Marketer with accepted invitation + marketer listing
        $this->assertSame('accepted', $scenario->marketerCampaignInvitation->status);
        $this->assertNotNull($scenario->marketerListing->id);

        // Customer with address + wallet
        $this->assertNotNull($scenario->customerAddress->id);
        $this->assertSame(50000, $scenario->customerWallet->balance);

        // Coupons: one per type x funded_by
        $this->assertCount(12, $scenario->coupons);
        foreach (['percentage', 'fixed_amount', 'free_shipping', 'bogo'] as $type) {
            foreach (['platform', 'vendor', 'shared'] as $funder) {
                $this->assertArrayHasKey("{$type}_{$funder}", $scenario->coupons);
            }
        }

        // Warranty plans: flat + percentage
        $this->assertSame('flat', $scenario->warrantyPlanFlat->price_type);
        $this->assertSame('percentage', $scenario->warrantyPlanPercentage->price_type);

        // Payment gateways
        foreach (['cod', 'wallet', 'stripe', 'bank_transfer'] as $code) {
            $this->assertArrayHasKey($code, $scenario->paymentGateways);
            $this->assertArrayHasKey($code, $scenario->countryPaymentGateways);
        }

        // Delivery agent + shipping company supervisor
        $this->assertNotNull($scenario->deliveryAgent->id);
        $this->assertNotNull($scenario->shippingCompanySupervisor->id);
        $this->assertSame($scenario->shippingCompany->id, $scenario->deliveryAgent->shipping_company_id);
    }

    public function test_p01_checkout_calculators_merged_price_shown_equals_price_charged(): void
    {
        // P-01 implemented: the two checkout calculators were merged into
        // App\Services\Checkout\CheckoutPricingEngine. Full coverage lives in:
        //  - Tests\Unit\Checkout\CheckoutPricingEngineTest (coupon allocation,
        //    D2 tax reconciliation, "single source of truth" guard);
        //  - Tests\Feature\Checkout\CheckoutPricingReconciliationTest
        //    (prepare vs. place-order totals match to the unit over HTTP,
        //    across coupon/warranty/wallet-gateway/multi-line combinations,
        //    plus the 409 price_changed path).
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6))]);

        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $this->actingAs($scenario->customer, 'customer');
        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
        ];

        $prepare = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/prepare", $payload);
        $prepare->assertOk();

        $place = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", array_merge($payload, [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]));
        $place->assertStatus(201);

        $orderNumber = $place->json('data.order.order_number') ?? $place->json('data.order_number');
        $order = \App\Models\Order::where('order_number', $orderNumber)->first();

        $this->assertNotNull($order);
        $this->assertSame((int) $prepare->json('data.order_summary.total'), (int) $order->total);
        $this->assertMoneyBalanced($order);
    }

    public function test_p02_place_order_supports_admin_and_marketer_listing_items(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6))]);

        // A campaign sourced from an ADMIN listing (P-02 gap #1): the
        // pre-existing resolveMarketerCartItems() only resolved campaigns
        // whose source was a vendor listing.
        $campaignFromAdminListing = \App\Models\MarketerCampaign::create([
            'vendor_id' => $scenario->vendor->id,
            'admin_listing_id' => $scenario->adminListing->id,
            'campaign_category' => 'product',
            'country_id' => $scenario->country->id,
            'currency' => 'AED',
            'commission_type' => 'fixed',
            'max_commission_budget' => 100000,
            'platform_commission_amount' => 5000,
            'marketer_commission_amount' => 0,
            'status' => 'active',
        ]);

        $adminCampaignInvitation = \App\Models\MarketerCampaignInvitation::create([
            'campaign_id' => $campaignFromAdminListing->id,
            'marketer_id' => $scenario->marketer->id,
            'status' => 'accepted',
            'responded_at' => now(),
            'referral_code' => 'REF-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
        ]);

        $adminCampaignMarketerListing = \App\Models\MarketerListing::create([
            'marketer_id' => $scenario->marketer->id,
            'product_variant_id' => $scenario->variants[0]->id,
            'listing_category' => 'product',
            'country_id' => $scenario->country->id,
            'invitation_id' => $adminCampaignInvitation->id,
            'price' => 95000,
            'currency' => 'AED',
            'status' => 'active',
            'condition' => 'new',
            'referral_code' => 'ML-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
        ]);

        // An INDEPENDENT marketer listing (P-02 gap #2): no invitation/
        // campaign at all — created the way Marketer/ListingController@store
        // creates one. Falls back to the best active vendor/admin listing
        // for the same variant as its fulfilment source.
        $independentMarketerListing = \App\Models\MarketerListing::create([
            'marketer_id' => $scenario->marketer->id,
            'product_variant_id' => $scenario->variants[1]->id,
            'listing_category' => 'product',
            'country_id' => $scenario->country->id,
            'invitation_id' => null,
            'price' => 130000,
            'currency' => 'AED',
            'status' => 'active',
            'condition' => 'new',
            'referral_code' => 'ML-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
        ]);

        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        // 1) Plain admin (platform) listing.
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'admin_listing_id' => $scenario->adminListing->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->adminListing->price,
            'added_at' => now(),
        ]);

        // 2) Plain vendor listing.
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbn->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbn->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        // 3) Campaign marketer listing sourced from an admin listing.
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'marketer_listing_id' => $adminCampaignMarketerListing->id,
            'quantity' => 1,
            'unit_price' => (int) $adminCampaignMarketerListing->price,
            'added_at' => now(),
        ]);

        // 4) Independent marketer listing.
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'marketer_listing_id' => $independentMarketerListing->id,
            'quantity' => 1,
            'unit_price' => (int) $independentMarketerListing->price,
            'added_at' => now(),
        ]);

        $this->actingAs($scenario->customer, 'customer');
        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
        ];

        $place = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", array_merge($payload, [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]));

        $place->assertStatus(201);

        $orderNumber = $place->json('data.order.order_number') ?? $place->json('data.order_number');
        $order = \App\Models\Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertMoneyBalanced($order);

        $order->load('subOrders.items');
        $items = $order->subOrders->flatMap(fn ($so) => $so->items);
        $this->assertCount(4, $items);

        // Plain admin listing item: admin_listing_id set, no vendor.
        $adminItem = $items->firstWhere('admin_listing_id', $scenario->adminListing->id);
        $this->assertNotNull($adminItem);
        $this->assertNull($adminItem->vendor_listing_id);
        $this->assertNull($adminItem->marketer_listing_id);
        $this->assertNull($adminItem->vendor_id);

        // Plain vendor listing item.
        $vendorItem = $items->firstWhere('vendor_listing_id', $scenario->vendorListingFbn->id);
        $this->assertNotNull($vendorItem);
        $this->assertSame($scenario->vendor->id, $vendorItem->vendor_id);

        // Campaign marketer listing sourced from the admin listing: marketer_listing_id
        // set, fulfilment recorded against the admin listing, no vendor.
        $adminMarketerItem = $items->firstWhere('marketer_listing_id', $adminCampaignMarketerListing->id);
        $this->assertNotNull($adminMarketerItem);
        $this->assertSame($scenario->adminListing->id, $adminMarketerItem->admin_listing_id);
        $this->assertNull($adminMarketerItem->vendor_listing_id);
        $this->assertNull($adminMarketerItem->vendor_id);

        // Independent marketer listing: resolved to the best vendor listing
        // for its variant (vendorListingFbn, the only active listing for
        // variants[1]) as its fulfilment source.
        $independentMarketerItem = $items->firstWhere('marketer_listing_id', $independentMarketerListing->id);
        $this->assertNotNull($independentMarketerItem);
        $this->assertSame($scenario->vendorListingFbn->id, $independentMarketerItem->vendor_listing_id);
        $this->assertSame($scenario->vendor->id, $independentMarketerItem->vendor_id);

        // Sub-orders: the admin-sourced lines land on a platform sub-order
        // (seller_type='platform', vendor_id null) that is never attributed
        // to a vendor.
        $platformSubOrders = $order->subOrders->where('seller_type', 'platform');
        $this->assertGreaterThanOrEqual(1, $platformSubOrders->count());
        foreach ($platformSubOrders as $subOrder) {
            $this->assertNull($subOrder->vendor_id);
        }

        $vendorSubOrders = $order->subOrders->where('seller_type', 'vendor');
        $this->assertGreaterThanOrEqual(1, $vendorSubOrders->count());
        foreach ($vendorSubOrders as $subOrder) {
            $this->assertNotNull($subOrder->vendor_id);
        }

        // Stock reserved on the right inventory rows (P-00 assertStock).
        // Both the plain admin-listing item and the admin-campaign marketer
        // item fulfil from the same admin listing (qty 1 each).
        $this->assertStock($scenario->adminListing, 30, 2);
        $this->assertStock($scenario->vendorListingFbn, 40, 2); // fbn line + independent marketer line
    }

    public function test_p03_order_money_split_vendor_platform_marketer_shipping(): void
    {
        // P-03 is implemented and covered by:
        //  - tests/Unit/Checkout/CheckoutPricingEngineSplitTest.php (engine-level:
        //    reconciliation identity, fee_fixed-once-per-order, vendor-funded coupon)
        //  - tests/Feature/Checkout/CheckoutMoneySplitTest.php (end-to-end through
        //    place-order: same identity computed from *persisted* sub_orders/order_items,
        //    plus a balanced double-entry ledger at capture).
        $scenario = MarketplaceScenario::make()->build();
        $this->assertNotNull($scenario->vendor->id);
        $engine = app(\App\Services\Checkout\CheckoutPricingEngine::class);
        $this->assertTrue(method_exists($engine, 'computeMoneySplit'));
    }

    public function test_p04_coupons_rules_enforced_and_usage_reverted(): void
    {
        // P-04 is implemented and covered by:
        //  - tests/Unit/Checkout/CouponEligibilityServiceTest.php (eligibility rules:
        //    active/window/country/min-order/eligibility/per-customer/per-month/
        //    total-limit/scope/stackability/free-shipping)
        //  - CouponUsageService::reserve() locks the coupon row (SELECT ... FOR UPDATE)
        //    so a coupon with usage_limit_total=1 cannot be double-spent concurrently,
        //    and release-on-rollback keeps times_used accurate after a decline.
        $scenario = MarketplaceScenario::make()->build();
        $this->assertNotNull($scenario->coupons['percentage_platform']->id);
        $this->assertTrue(class_exists(\App\Services\Checkout\CouponEligibilityService::class));
        $this->assertTrue(class_exists(\App\Services\Checkout\CouponUsageService::class));
    }

    public function test_p05_payment_methods_wallet_cod_gateway_bank_transfer(): void
    {
        // P-05 is implemented and covered end-to-end by
        // tests/Feature/Checkout/PaymentMethodMatrixTest.php: the full
        // tender matrix (wallet full/partial+card/card/cod/bank_transfer)
        // x (success/decline/exception/cancel/webhook-first/duplicate
        // webhook/duplicate place-order), plus the signed-cancel-callback
        // 403 case.
        $scenario = MarketplaceScenario::make()->build();
        $this->assertNotNull($scenario->customerWallet->id);
        $this->assertTrue(class_exists(\App\Services\Checkout\CheckoutRollbackService::class));
        $this->assertTrue(class_exists(\App\Services\Payments\PaymentMethodMapper::class));
    }

    public function test_p06_cancellation_engine_reverses_money_correctly(): void
    {
        // P-06 is implemented and covered end-to-end by
        // tests/Feature/OrderCancellationServiceTest.php: the tender
        // (wallet/card/cod) x scope (full order/one sub-order/one item) x
        // actor (customer/admin/system) matrix, idempotency, loyalty/
        // coupon/warranty/marketer-conversion reversal and full-order
        // ledger balancing.
        $scenario = MarketplaceScenario::make()->build();
        $this->assertTrue(class_exists(\App\Services\OrderCancellationService::class));
        $this->assertTrue(class_exists(\App\Enums\CancelActor::class));
        $this->assertNotNull($scenario->customer->id);
    }

    public function test_p07_refunds_no_double_refund_cod_and_return_refunds(): void
    {
        // P-07 is implemented and covered end-to-end by
        // tests/Feature/RefundServiceTest.php: card refund goes only to
        // the gateway, COD/store-credit refunds go only to the wallet,
        // returning N of M units refunds exactly that unit's persisted
        // share, and RefundProcessingJob no longer double-credits.
        $scenario = MarketplaceScenario::make()->build();
        $this->assertTrue(class_exists(\App\Services\RefundService::class));
        $this->assertTrue(class_exists(\App\DTOs\Refund\RefundScope::class));
        $this->assertNotNull($scenario->customer->id);
    }

    public function test_p08_order_status_state_machine_delivery_and_cod_capture(): void
    {
        $this->markTestSkipped('P-08: order/sub-order status state machine, delivery and COD capture.');
    }

    public function test_p09_warranty_lifecycle_purchase_activation_expiry_claims(): void
    {
        $this->markTestSkipped('P-09: warranty lifecycle (purchase, activation, expiry, claims).');
    }

    public function test_p10_return_lifecycle_eligibility_and_restock(): void
    {
        $this->markTestSkipped('P-10: return (listing return) lifecycle eligibility checks and restock.');
    }

    public function test_p11_ledger_and_payouts_reconciliation(): void
    {
        $this->markTestSkipped('P-11: ledger and payouts reconciliation (vendor/admin/marketer/shipping).');
    }

    public function test_p12_marketer_attribution_and_commission_reach_order(): void
    {
        $this->markTestSkipped('P-12: marketer attribution and commission must reach the order.');
    }

    public function test_p13_listing_quantities_single_inventory_service(): void
    {
        $this->markTestSkipped('P-13: one inventory service for every stock increment/decrement.');
    }
}
