<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Order;
use App\Notifications\Customer\OrderCancelled;
use App\Notifications\Customer\OrderRefundProcessed;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Customer order notifications must build a link for the order's own country (they once threw
 * UrlGenerationException). R4 (retest 2026-11-24): that link is the storefront order page
 * (FRONTEND_URL/{site_code}-{lang}/orders/{number}), never the customer JSON API.
 */
class CustomerOrderNotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_notifications_link_to_the_order_in_its_country(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'om-'.Str::lower(Str::random(6))]);

        $this->actingAs($scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $orderNumber = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        config(['app.frontend_url' => 'https://shop.example.test/']);
        $scenario->customer->forceFill(['locale' => 'en'])->save();
        $siteCode = $scenario->country->site_code;
        $expected = "https://shop.example.test/{$siteCode}-en/orders/{$orderNumber}";

        $cancelled = (new OrderCancelled($order->subOrders()->firstOrFail()))->notificationData($scenario->customer);
        $this->assertSame($expected, $cancelled['url']);

        $mail = (new OrderCancelled($order->subOrders()->firstOrFail()))->toMail($scenario->customer);
        $this->assertSame($expected, $mail->actionUrl);
        $this->assertStringNotContainsString('/api/', (string) $mail->render());

        $scenario->customer->forceFill(['locale' => 'ar'])->save();
        $this->assertSame(
            "https://shop.example.test/{$siteCode}-ar/orders/{$orderNumber}",
            (new OrderCancelled($order->subOrders()->firstOrFail()))->notificationData($scenario->customer->fresh())['url'],
        );

        $refund = $order->refunds()->create([
            'amount' => 1, 'currency' => $order->currency, 'reason' => 'customer_request',
            'refund_type' => 'partial', 'vendor_charged_back' => false, 'status' => 'completed',
        ]);
        $this->assertSame(
            "https://shop.example.test/{$siteCode}-ar/orders/{$orderNumber}/summary",
            (new OrderRefundProcessed($refund))->notificationData($scenario->customer->fresh())['url'],
        );
    }
}
