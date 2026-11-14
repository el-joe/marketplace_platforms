<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Order;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * A customer cancelling a pre-shipment order goes through the cancellation engine, so the stock the
 * order reserved goes back on sale (it used to stay reserved forever).
 */
class CustomerOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_a_placed_order_releases_its_reserved_stock(): void
    {
        Notification::fake();
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'om-'.Str::lower(Str::random(6))]);
        $inventory = $scenario->vendorListingFbpInventory;
        $reservedBefore = $inventory->fresh()->quantity_reserved;

        $this->actingAs($scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 2,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $base = "/api/customer/v1/{$scenario->country->site_code}";
        $orderNumber = $this->postJson("{$base}/checkout/place-order", [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');
        $this->assertSame($reservedBefore + 2, $inventory->fresh()->quantity_reserved);

        $this->postJson("{$base}/orders/{$orderNumber}/cancel", ['reason' => 'Changed my mind'])->assertOk();

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('cancelled', $order->subOrders()->firstOrFail()->status->value);
        $this->assertSame($reservedBefore, $inventory->fresh()->quantity_reserved);
    }
}
