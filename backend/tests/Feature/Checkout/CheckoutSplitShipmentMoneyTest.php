<?php

namespace Tests\Feature\Checkout;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * One vendor whose cart lines use different shipping methods is placed as one sub-order per method.
 * The vendor's tax, shipping, commission and payout must be divided across those sub-orders — they
 * used to be copied onto each one, so Σ sub_orders.tax and Σ vendor_payout were a multiple of the order.
 */
class CheckoutSplitShipmentMoneyTest extends TestCase
{
    use AssertsOrderMoney;
    use RefreshDatabase;

    public function test_a_vendor_split_across_shipping_methods_reconciles_with_the_order(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $country = $scenario->country;

        $standard = ShippingMethod::create(['name' => 'Standard', 'code' => 'standard-'.Str::random(4), 'is_active' => true]);
        $express = ShippingMethod::create(['name' => 'Express', 'code' => 'express-'.Str::random(4), 'is_active' => true]);

        $cart = app(CartService::class)->getOrCreateCart($scenario->customer, $country->id, $country->currency_code);
        foreach ([[$scenario->vendorListingFbp, 2, $standard], [$scenario->vendorListingFbn, 1, $express]] as [$listing, $quantity, $method]) {
            CartItem::create([
                'cart_id' => $cart->id,
                'vendor_listing_id' => $listing->id,
                'quantity' => $quantity,
                'unit_price' => (int) $listing->getRawOriginal('price'),
                'selected_shipping_method_id' => $method->id,
                'added_at' => now(),
            ]);
        }

        $this->actingAs($scenario->customer, 'customer');
        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
        ];

        $this->postJson("/api/customer/v1/{$country->site_code}/checkout/prepare", $payload)->assertOk();
        $response = $this->postJson("/api/customer/v1/{$country->site_code}/checkout/place-order", $payload + [
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))
            ->with(['subOrders', 'items'])
            ->firstOrFail();

        $this->assertCount(2, $order->subOrders);
        $this->assertSame([$scenario->vendor->id], $order->subOrders->pluck('vendor_id')->unique()->values()->all());
        $this->assertMoneyBalanced($order);
        $this->assertEqualsWithDelta((float) $order->shipping, (float) $order->subOrders->sum('shipping'), 0.0001);
        $this->assertEqualsWithDelta(
            (float) $order->items->sum('platform_commission_after_discount'),
            (float) $order->subOrders->sum('platform_commission'),
            0.0001,
        );
        $this->assertLessThan((float) $order->subtotal, (float) $order->subOrders->sum('vendor_payout'));

        foreach ($order->subOrders as $subOrder) {
            $items = $order->items->where('sub_order_id', $subOrder->id);
            $this->assertEqualsWithDelta((float) $items->sum('line_tax'), (float) $subOrder->tax, 0.0001);
        }
    }
}
