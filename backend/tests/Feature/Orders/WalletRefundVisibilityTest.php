<?php

namespace Tests\Feature\Orders;

use App\Enums\CancelActor;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Notifications\Customer\OrderRefundProcessed;
use App\Services\Customer\CartService;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * R2 (client retest 2026-11-24): cancelling a wallet-paid order re-credited the wallet silently, so
 * the order kept showing only the original "succeeded" sale and payment_status "captured".
 */
class WalletRefundVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
    }

    private function placeOrder(string $gatewayCode, bool $withSecondItem = false): Order
    {
        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        $listings = $withSecondItem
            ? [$this->scenario->vendorListingFbp, $this->scenario->vendorListingFbn]
            : [$this->scenario->vendorListingFbp];
        foreach ($listings as $listing) {
            CartItem::create([
                'cart_id' => $cart->id,
                'vendor_listing_id' => $listing->id,
                'quantity' => 1,
                'unit_price' => (int) $listing->getRawOriginal('price'),
                'added_at' => now(),
            ]);
        }

        $number = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');

        return Order::where('order_number', $number)->firstOrFail();
    }

    public function test_full_cancel_of_a_wallet_paid_order_records_a_visible_wallet_refund(): void
    {
        Notification::fake();
        $order = $this->placeOrder('wallet');

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'Changed my mind');

        $order->refresh();
        $this->assertSame('refunded', $order->payment_status->value);

        $refund = Refund::where('order_id', $order->id)->sole();
        $this->assertSame('completed', $refund->status->value);
        $this->assertEqualsWithDelta((float) $order->total, (float) $refund->amount, 0.0001);

        $refundTransaction = PaymentTransaction::where('order_id', $order->id)->where('type', 'refund')->sole();
        $this->assertSame('wallet', $refundTransaction->gateway);
        $this->assertSame('succeeded', $refundTransaction->status->value);
        $this->assertSame($refundTransaction->id, $refund->refund_transaction_id);

        Notification::assertSentTo($this->scenario->customer, OrderRefundProcessed::class);

        $this->getJson("/api/customer/v1/{$this->scenario->country->site_code}/orders/{$order->order_number}")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonCount(1, 'data.refunds')
            ->assertJsonPath('data.refunds.0.method', 'wallet')
            ->assertJsonPath('data.refunds.0.status', 'completed');

        foreach (['orders.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('orders.view');

        $this->actingAs($admin, 'admin')->get(route('admin.orders.show', $order->id))
            ->assertOk()
            ->assertSee($refundTransaction->gateway_transaction_id)
            ->assertSee(__('admin.orders.tx_type_refund'));
    }

    public function test_cancelling_one_of_two_items_of_a_wallet_paid_order_marks_it_partially_refunded(): void
    {
        Notification::fake();
        $order = $this->placeOrder('wallet', withSecondItem: true);
        $item = $order->items()->where('vendor_listing_id', $this->scenario->vendorListingFbp->id)->firstOrFail();

        app(OrderCancellationService::class)->cancel(collect([$item]), CancelActor::Customer, 'one is enough');

        $order->refresh();
        $this->assertSame('partially_refunded', $order->payment_status->value);
        $refund = Refund::where('order_id', $order->id)->sole();
        $this->assertSame('partial', $refund->refund_type->value);
        $this->assertLessThan((float) $order->total, (float) $refund->amount);
    }

    public function test_cancelling_an_unpaid_cod_order_records_no_refund(): void
    {
        Notification::fake();
        $order = $this->placeOrder('cod');

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'no longer needed');

        $this->assertSame(0, Refund::where('order_id', $order->id)->count());
        $this->getJson("/api/customer/v1/{$this->scenario->country->site_code}/orders/{$order->order_number}")
            ->assertOk()
            ->assertJsonCount(0, 'data.refunds');
        Notification::assertNotSentTo($this->scenario->customer, OrderRefundProcessed::class);
    }
}
