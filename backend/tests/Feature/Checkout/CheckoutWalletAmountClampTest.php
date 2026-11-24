<?php

namespace Tests\Feature\Checkout;

use App\Enums\WalletOwnerType;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Services\Customer\CartService;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakePaymentGateway;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * R1 (client retest 2026-11-24): a wallet balance above the order total (20 / 25.75 vs 15.75 incl.
 * VAT) was rejected with "wallet amount cannot exceed order total" because the storefront sent the
 * whole balance. The server now debits min(requested, payable total, balance).
 */
class CheckoutWalletAmountClampTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        // 15 AED + 5% VAT = 15.75, the QA example.
        $this->scenario->vendorListingFbp->update(['price' => 15]);

        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $this->scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => 15,
            'added_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        PaymentGatewayFactory::fake(null);
        parent::tearDown();
    }

    private function setWalletBalance(int|float $balance): void
    {
        $this->scenario->customerWallet->update(['balance' => $balance]);
    }

    private function walletBalance(): float
    {
        return (float) Wallet::where('owner_type', WalletOwnerType::Customer)
            ->where('owner_id', $this->scenario->customer->id)
            ->value('balance');
    }

    private function previewTotal(string $gatewayCode, array $extra = []): float
    {
        $response = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/prepare", array_merge([
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways[$gatewayCode]->id,
        ], $extra))->assertOk();

        return (float) $response->json('data.order_summary.total');
    }

    private function placeOrder(string $gatewayCode, array $extra = []): TestResponse
    {
        return $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", array_merge([
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra));
    }

    private function orderFrom(TestResponse $response): Order
    {
        $number = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $number)->firstOrFail();
    }

    /**
     * @return array<string, array{float}>
     */
    public static function balancesAboveTotal(): array
    {
        return ['balance 20' => [20.0], 'balance 25.75' => [25.75]];
    }

    #[DataProvider('balancesAboveTotal')]
    public function test_wallet_balance_above_total_pays_the_whole_order_and_debits_only_the_total(float $balance): void
    {
        $this->setWalletBalance($balance);
        $total = $this->previewTotal('stripe');

        $fake = new FakePaymentGateway;
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        // The storefront used to send the whole balance.
        $response = $this->placeOrder('stripe', ['wallet_amount_to_use' => $balance]);

        $response->assertCreated();
        $order = $this->orderFrom($response);
        $this->assertEqualsWithDelta($total, (float) $order->total, 0.0001);
        $this->assertEqualsWithDelta((float) $order->total, (float) $order->wallet_amount_used, 0.0001);
        $this->assertSame('captured', $order->payment_status->value);
        $this->assertSame('wallet', $order->payment_method);
        $this->assertEqualsWithDelta($balance - (float) $order->total, $this->walletBalance(), 0.0001);
        $this->assertCount(0, $fake->initiateCalls, 'nothing is left for the card gateway');
    }

    public function test_wallet_balance_equal_to_total_pays_the_whole_order(): void
    {
        $total = $this->previewTotal('wallet');
        $this->setWalletBalance($total);

        $response = $this->placeOrder('wallet', ['wallet_amount_to_use' => $total]);

        $response->assertCreated();
        $order = $this->orderFrom($response);
        $this->assertSame('captured', $order->payment_status->value);
        $this->assertEqualsWithDelta(0, $this->walletBalance(), 0.0001);
    }

    public function test_wallet_balance_below_total_is_used_and_the_card_is_charged_the_rest(): void
    {
        $this->setWalletBalance(5);
        $fake = new FakePaymentGateway;
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        // A client asking for more than the balance gets the balance, not an error.
        $response = $this->placeOrder('stripe', ['wallet_amount_to_use' => 50]);

        $response->assertCreated();
        $order = $this->orderFrom($response);
        $this->assertEqualsWithDelta(5, (float) $order->wallet_amount_used, 0.0001);
        $this->assertEqualsWithDelta(0, $this->walletBalance(), 0.0001);
        $this->assertCount(1, $fake->initiateCalls);
        $this->assertEqualsWithDelta((float) $order->total - 5, $fake->initiateCalls[0]['data']->amountCents, 0.0001);
    }

    public function test_wallet_gateway_with_balance_above_total_after_a_coupon_debits_the_discounted_total(): void
    {
        $this->setWalletBalance(25.75);
        $coupon = $this->scenario->coupons['percentage_platform'];
        $total = $this->previewTotal('wallet', ['coupon_code' => $coupon->code]);

        $response = $this->placeOrder('wallet', ['wallet_amount_to_use' => 25.75, 'coupon_code' => $coupon->code]);

        $response->assertCreated();
        $order = $this->orderFrom($response);
        $this->assertGreaterThan(0, (float) $order->discount);
        $this->assertEqualsWithDelta($total, (float) $order->total, 0.0001);
        $this->assertEqualsWithDelta((float) $order->total, (float) $order->wallet_amount_used, 0.0001);
        $this->assertEqualsWithDelta(25.75 - (float) $order->total, $this->walletBalance(), 0.0001);
    }

    public function test_cod_with_wallet_balance_above_total_is_paid_fully_by_wallet_with_no_cash_to_collect(): void
    {
        $this->setWalletBalance(20);

        $response = $this->placeOrder('cod', ['wallet_amount_to_use' => 20]);

        $response->assertCreated();
        $order = $this->orderFrom($response);
        $this->assertSame('captured', $order->payment_status->value);
        $this->assertFalse(PaymentTransaction::where('order_id', $order->id)->where('gateway', 'cod')->exists());
    }

    public function test_cod_with_wallet_balance_below_total_is_refused_with_the_localized_rule(): void
    {
        $this->setWalletBalance(5);

        $this->placeOrder('cod', ['wallet_amount_to_use' => 5])
            ->assertStatus(422)
            ->assertJsonPath('message', __('common.exceptions.checkout.cod_wallet_rule'));

        $this->withHeader('Accept-Language', 'ar')
            ->placeOrder('cod', ['wallet_amount_to_use' => 5])
            ->assertStatus(422)
            ->assertJsonPath('message', trans('common.exceptions.checkout.cod_wallet_rule', [], 'ar'));

        $this->assertEqualsWithDelta(5, $this->walletBalance(), 0.0001);
    }
}
