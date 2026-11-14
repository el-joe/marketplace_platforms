<?php

namespace Tests\Feature\Admin;

use App\Enums\WalletOwnerType;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Wallet;
use App\Services\Customer\CartService;
use App\Services\OrderInterventionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * An admin refund from the order screen used to stop at 'approved': it marked the order refunded
 * without moving any money, and nothing ever settled it. It now settles (wallet for COD, gateway
 * for card), and refuses orders with nothing collected or refunds beyond what was paid.
 */
class AdminOrderRefundSettlementTest extends TestCase
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

    private function placedCodOrder(): Order
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

    private function walletBalance(): int|float
    {
        return Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $this->scenario->customer->id)
            ->value('balance') ?? 0;
    }

    private function refund(Order $order, string $type = 'full', int|float|null $amount = null): Refund
    {
        return app(OrderInterventionService::class)->processRefund(
            $order->fresh(), $type, $amount, 'damaged', null, null, false, (string) Admin::factory()->create()->id,
        );
    }

    public function test_a_full_admin_refund_on_a_collected_cod_order_is_settled_to_the_wallet(): void
    {
        $order = $this->placedCodOrder();
        $order->update(['payment_status' => 'captured']);
        $walletBefore = $this->walletBalance();

        $refund = $this->refund($order)->fresh();

        $this->assertSame('completed', $refund->status->value);
        $this->assertSame('admin', $refund->initiated_by_type);
        $this->assertNull($refund->initiated_by_customer_id);
        $this->assertEqualsWithDelta((float) $walletBefore + (float) $refund->net_refund, (float) $this->walletBalance(), 0.0001);
        $this->assertSame('refunded', $order->fresh()->payment_status->value);
    }

    public function test_a_refund_on_an_order_with_nothing_collected_is_refused(): void
    {
        $order = $this->placedCodOrder();

        try {
            $this->refund($order);
            $this->fail('A refund on an uncollected COD order must be refused.');
        } catch (ValidationException) {
            $this->assertSame(0, Refund::where('order_id', $order->id)->count());
            $this->assertSame('pending', $order->fresh()->payment_status->value);
        }
    }

    public function test_refunds_cannot_exceed_what_the_customer_paid(): void
    {
        $order = $this->placedCodOrder();
        $order->update(['payment_status' => 'captured']);
        $this->refund($order);

        try {
            $this->refund($order, 'partial', 1);
            $this->fail('A refund beyond the order total must be refused.');
        } catch (ValidationException) {
            $this->assertSame(1, Refund::where('order_id', $order->id)->count());
        }
    }
}
