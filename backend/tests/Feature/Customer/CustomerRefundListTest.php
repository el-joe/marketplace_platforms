<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * "My refunds" lists every refund on the customer's own orders, including the ones an admin issued
 * after inspecting a return (those carry no initiated_by_customer_id).
 */
class CustomerRefundListTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'om-'.Str::lower(Str::random(6))]);
    }

    private function placedOrder(): Order
    {
        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $this->scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $this->scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $orderNumber = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function adminIssuedRefund(Order $order): Refund
    {
        return Refund::create([
            'order_id' => $order->id,
            'sub_order_id' => null,
            'amount' => 5,
            'currency' => $order->currency,
            'reason' => 'damaged',
            'refund_type' => 'partial',
            'initiated_by_customer_id' => null,
            'initiated_by_type' => 'admin',
            'initiated_by_id' => (string) Str::uuid(),
            'vendor_charged_back' => false,
            'status' => 'completed',
        ]);
    }

    public function test_customer_sees_a_refund_an_admin_issued_on_their_order(): void
    {
        $refund = $this->adminIssuedRefund($this->placedOrder());
        $base = "/api/customer/v1/{$this->scenario->country->site_code}/refunds";

        $this->getJson($base)->assertOk()->assertJsonFragment(['id' => $refund->id]);
        $this->getJson("{$base}/{$refund->id}")->assertOk();
    }
}
