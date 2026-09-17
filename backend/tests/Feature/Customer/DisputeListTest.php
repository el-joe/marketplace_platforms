<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-26 follow-up (enhancement.md Phase H): the customer disputes list page
 * moved from mock data to GET {country}/disputes. This locks down that the
 * endpoint is scoped to the authenticated customer, paginated, and shaped
 * the way DisputeResource + the frontend's Dispute type expect.
 */
class DisputeListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Notification::fake();
    }

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function placeOrder(MarketplaceScenario $scenario): Order
    {
        $this->actingAs($scenario->customer, 'customer');

        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['wallet']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    public function test_customer_can_list_only_their_own_disputes(): void
    {
        $scenario = $this->buildScenario();
        $order = $this->placeOrder($scenario);
        $subOrder = $order->subOrders()->first();

        $dispute = Dispute::create([
            'dispute_number' => 'DSP-'.strtoupper(Str::random(8)),
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'customer_id' => $scenario->customer->id,
            'vendor_id' => $subOrder->vendor_id,
            'reason' => 'item_not_received',
            'description' => 'Never arrived',
            'status' => 'under_review',
        ]);

        // Another customer's dispute (on the same order/vendor) must never
        // show up in this list — scoping is by customer_id, not order/vendor.
        $otherCustomer = \App\Models\Customer::create([
            'name' => 'Other Customer',
            'email' => 'other-'.Str::random(8).'@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'country_id' => $scenario->country->id,
            'status' => 'active',
        ]);
        Dispute::create([
            'dispute_number' => 'DSP-'.strtoupper(Str::random(8)),
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'customer_id' => $otherCustomer->id,
            'vendor_id' => $subOrder->vendor_id,
            'reason' => 'wrong_item',
            'description' => 'Not mine',
            'status' => 'open',
        ]);

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/disputes");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.id', $dispute->id);
        $response->assertJsonPath('data.items.0.dispute_number', $dispute->dispute_number);
        $response->assertJsonPath('data.items.0.order_number', $order->order_number);
        $response->assertJsonPath('data.items.0.reason', 'item_not_received');
        $response->assertJsonPath('data.items.0.status', 'under_review');
        $response->assertJsonPath('data.meta.total', 1);
    }

    public function test_disputes_list_requires_customer_auth(): void
    {
        $scenario = $this->buildScenario();

        $response = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/disputes");

        $response->assertStatus(401);
    }
}
