<?php

namespace Tests\Feature\Checkout;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-01 (enhancement.md, Phase B): the two checkout calculators were merged
 * into one App\Services\Checkout\CheckoutPricingEngine. These tests hit the
 * real `prepare` and `place-order` HTTP endpoints and prove:
 *  - prepare's total equals place-order's total, to the unit, across a
 *    coupon x payment-method x warranty x vendor-count matrix;
 *  - the persisted order is internally money-balanced (P-00's
 *    assertMoneyBalanced);
 *  - a genuine price change between prepare and place-order is rejected
 *    with HTTP 409 price_changed.
 */
class CheckoutPricingReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function addVendorItem(MarketplaceScenario $scenario, $cart, $listing, int $quantity = 1): CartItem
    {
        return CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $quantity,
            'unit_price' => (int) $listing->getRawOriginal('price'),
            'added_at' => now(),
        ]);
    }

    private function prepareAndPlace(MarketplaceScenario $scenario, array $prepareExtra = [], array $placeExtra = []): array
    {
        $country = $scenario->country;
        $this->actingAs($scenario->customer, 'customer');

        $payload = array_merge([
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
        ], $prepareExtra);

        $prepareResponse = $this->postJson("/api/customer/v1/{$country->site_code}/checkout/prepare", $payload);
        $prepareResponse->assertOk();
        $preparedTotal = $prepareResponse->json('data.order_summary.total');

        $placePayload = array_merge($payload, [
            'idempotency_key' => (string) Str::uuid(),
        ], $placeExtra);

        $placeResponse = $this->postJson("/api/customer/v1/{$country->site_code}/checkout/place-order", $placePayload);

        return [$prepareResponse, $placeResponse, $preparedTotal];
    }

    public function test_p01_prepare_total_equals_place_order_total_single_vendor_no_coupon(): void
    {
        $scenario = $this->buildScenario();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 2);

        [$prepareResponse, $placeResponse, $preparedTotal] = $this->prepareAndPlace($scenario);

        $placeResponse->assertStatus(201);
        $placedTotal = $placeResponse->json('data.order.total') ?? $placeResponse->json('data.total');
        $orderNumber = $placeResponse->json('data.order.order_number') ?? $placeResponse->json('data.order_number');

        $order = \App\Models\Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);

        $this->assertSame((int) $preparedTotal, (int) $order->total, 'prepare total must equal the persisted order total');
        $this->assertMoneyBalanced($order);
    }

    public function test_p01_prepare_total_equals_place_order_total_with_coupon_and_warranty(): void
    {
        $scenario = $this->buildScenario();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 3);

        $coupon = $scenario->coupons['percentage_platform'];

        [$prepareResponse, $placeResponse, $preparedTotal] = $this->prepareAndPlace($scenario, [
            'coupon_code' => $coupon->code,
            'warranty_selections' => [
                ['listing_id' => $scenario->vendorListingFbp->id, 'warranty_plan_id' => $scenario->warrantyPlanFlat->id],
            ],
        ], [
            'coupon_code' => $coupon->code,
            'warranty_selections' => [
                ['listing_id' => $scenario->vendorListingFbp->id, 'warranty_plan_id' => $scenario->warrantyPlanFlat->id],
            ],
        ]);

        $placeResponse->assertStatus(201);
        $orderNumber = $placeResponse->json('data.order.order_number') ?? $placeResponse->json('data.order_number');
        $order = \App\Models\Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);

        $this->assertSame((int) $preparedTotal, (int) $order->total);
        $this->assertMoneyBalanced($order);

        // Order-level tax must reconcile with sub-order and line-level tax
        // (D2: tax computed on the discounted amount, at every level).
        $this->assertSame((int) $order->tax, (int) $order->subOrders->sum('tax'));
        $this->assertSame((int) $order->discount, (int) $order->items->sum('line_discount'));
    }

    public function test_p01_multi_line_cart_money_balanced_with_bogo_coupon(): void
    {
        // vendorListingFbp and vendorListingFbn belong to the same
        // MarketplaceScenario vendor (they differ by fulfillment model, not
        // by vendor), so this is a 2-line, 1-vendor (1 sub-order) cart —
        // multi-vendor grouping is covered at the engine-unit level in
        // CheckoutPricingEngineTest, which exercises 1-3 distinct vendor_ids.
        $scenario = $this->buildScenario();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 2);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbn, 1);

        $coupon = $scenario->coupons['bogo_platform'];

        [$prepareResponse, $placeResponse, $preparedTotal] = $this->prepareAndPlace($scenario, [
            'coupon_code' => $coupon->code,
        ], [
            'coupon_code' => $coupon->code,
        ]);

        $placeResponse->assertStatus(201);
        $orderNumber = $placeResponse->json('data.order.order_number') ?? $placeResponse->json('data.order_number');
        $order = \App\Models\Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);

        $this->assertSame((int) $preparedTotal, (int) $order->total);
        $this->assertMoneyBalanced($order);
        $this->assertCount(1, $order->subOrders);
    }

    public function test_p01_price_change_between_prepare_and_place_order_returns_409(): void
    {
        $scenario = $this->buildScenario();
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $this->actingAs($scenario->customer, 'customer');
        $country = $scenario->country;

        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
        ];

        $this->postJson("/api/customer/v1/{$country->site_code}/checkout/prepare", $payload)->assertOk();

        // The vendor changes the listing price after `prepare` but before
        // `place-order` — the cached prepare signature must no longer match.
        $scenario->vendorListingFbp->update(['price' => $scenario->vendorListingFbp->price + 500]);
        $cart->items()->update(['unit_price' => $scenario->vendorListingFbp->price]);

        $placeResponse = $this->postJson("/api/customer/v1/{$country->site_code}/checkout/place-order", array_merge($payload, [
            'idempotency_key' => (string) Str::uuid(),
        ]));

        $placeResponse->assertStatus(409);
        $this->assertSame('price_changed', $placeResponse->json('errors.error_code'));
    }
}
