<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Notifications\Customer\OrderCancelled;
use App\Notifications\Customer\OrderConfirmed;
use App\Notifications\Customer\OrderDelivered;
use App\Notifications\Customer\OrderOutForDelivery;
use App\Notifications\Customer\OrderRefunded;
use App\Notifications\Customer\OrderRefundProcessed;
use App\Notifications\Customer\OrderShipped;
use App\Notifications\Customer\PaymentFailed;
use App\Notifications\Customer\WalletCredited;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * R4 / R12 (client retest 2026-11-24): the refund email and every cancellation email linked to
 * https://api.nawyweb.com/api/customer/v1/uae/orders/{number}. Every order-related customer email is
 * rendered here with production-like hosts (APP_URL on the api.* host) and no link may point at the
 * API host or an /api/ path; the order buttons must open the storefront order page.
 */
class CustomerOrderMailLinksTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'https://shop.example.test';

    public function test_every_order_related_customer_email_links_only_to_the_storefront(): void
    {
        config(['app.url' => 'https://api.example.test', 'app.frontend_url' => self::FRONTEND.'/']);
        [$order, $customer] = $this->placeOrder();
        $subOrder = $order->subOrders()->firstOrFail();
        $refund = $order->refunds()->create([
            'amount' => 1, 'currency' => $order->currency, 'reason' => 'customer_request',
            'refund_type' => 'partial', 'vendor_charged_back' => false, 'status' => 'completed',
        ]);
        $orderPage = self::FRONTEND."/{$order->country->site_code}-en/orders/{$order->order_number}";

        /** @var array<string, array{Notification, string}> $mails  name => [notification, expected button url] */
        $mails = [
            'confirmed' => [new OrderConfirmed($subOrder), $orderPage],
            'shipped' => [new OrderShipped($subOrder), $orderPage],
            'out for delivery' => [new OrderOutForDelivery($subOrder, '123456'), $orderPage],
            'delivered' => [new OrderDelivered($subOrder), $orderPage],
            'cancelled (whole sub-order)' => [new OrderCancelled($subOrder, 'customer request'), $orderPage],
            'cancelled (some items)' => [new OrderCancelled($subOrder, 'out of stock', 1), $orderPage],
            'refunded' => [new OrderRefunded($subOrder), $orderPage],
            'refund processed' => [new OrderRefundProcessed($refund), $orderPage.'/summary'],
            'payment failed' => [new PaymentFailed($order, 'declined'), $orderPage],
            'wallet credited' => [new WalletCredited(5, 'Refund for order', $customer->id), self::FRONTEND."/{$customer->country->site_code}-en/noon-credits"],
        ];

        foreach ($mails as $name => [$notification, $expectedUrl]) {
            $mail = $notification->toMail($customer);
            $this->assertSame($expectedUrl, $mail->actionUrl, "{$name}: button must open the storefront page");

            $html = (string) $mail->render();
            preg_match_all('/href="([^"]+)"/i', $html, $matches);
            $this->assertNotEmpty($matches[1], "{$name}: rendered mail has links");
            foreach ($matches[1] as $href) {
                $href = html_entity_decode($href);
                $this->assertStringNotContainsString('api.', (string) parse_url($href, PHP_URL_HOST), "{$name}: {$href} points at the API host");
                $this->assertStringNotContainsString('/api/', $href, "{$name}: {$href} is an API path");
            }
        }
    }

    /**
     * @return array{Order, Customer}
     */
    private function placeOrder(): array
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $scenario->customer->forceFill(['locale' => 'en', 'country_id' => $scenario->country->id])->save();

        $this->actingAs($scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $number = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');

        return [Order::where('order_number', $number)->firstOrFail(), $scenario->customer->fresh()];
    }
}
