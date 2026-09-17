<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Models\CartItem;
use App\Models\MarketerCampaignConversion;
use App\Models\Order;
use App\Models\Wallet;
use App\Services\OrderCancellationService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-12: marketer attribution + conversion lifecycle.
 *  - buying a marketer-listing cart item attributes per order item and
 *    creates a 'pending' conversion at placement, no session()/gateway
 *    click required;
 *  - the conversion approves (and credits the marketer wallet
 *    pending_balance) once delivered AND the return window has passed;
 *  - cancelling reverses a still-pending/approved conversion and claws
 *    back any wallet credit;
 *  - a campaign whose max_commission_budget is exhausted auto-pauses.
 */
class MarketerAttributionConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function placeMarketerListingOrder(MarketplaceScenario $scenario, int $qty = 1): Order
    {
        $this->actingAs($scenario->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        CartItem::create([
            'cart_id' => $cart->id,
            'marketer_listing_id' => $scenario->marketerListing->id,
            'quantity' => $qty,
            'unit_price' => (int) $scenario->marketerListing->price,
            'added_at' => now(),
        ]);

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function deliverAllSubOrders(Order $order): void
    {
        $machine = app(OrderStateMachine::class);
        $order->load('subOrders');

        foreach ($order->subOrders as $subOrder) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($subOrder, $step, CancelActor::System);
            }
        }
    }

    public function test_buying_from_a_marketer_listing_is_attributed_without_a_referral_click(): void
    {
        $scenario = $this->buildScenario();
        $order = $this->placeMarketerListingOrder($scenario);

        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);

        $this->assertNotNull($item);
        $this->assertSame($scenario->marketerCampaignInvitation->id, $item->marketer_campaign_invitation_id);

        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->first();
        $this->assertNotNull($conversion);
        $this->assertSame('pending', $conversion->status);
        $this->assertGreaterThan(0, $conversion->commission_amount);
        $this->assertSame($scenario->marketerCampaign->id, $conversion->campaign_id);
    }

    public function test_conversion_approves_and_credits_wallet_after_delivery_and_return_window(): void
    {
        $scenario = $this->buildScenario();
        $order = $this->placeMarketerListingOrder($scenario);

        $this->deliverAllSubOrders($order);

        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);
        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->first();
        $this->assertNotNull($conversion);

        // Return window not passed yet — still pending.
        (new ApproveMarketerConversionsJob())->handle();
        $conversion->refresh();
        $this->assertSame('pending', $conversion->status);

        // Move the return window into the past and re-run the job.
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        (new ApproveMarketerConversionsJob())->handle();

        $conversion->refresh();
        $this->assertSame('approved', $conversion->status);
        $this->assertNotNull($conversion->wallet_credited_at);

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $scenario->marketer->id)->first();
        $this->assertNotNull($wallet);
        $totalCommission = (int) $conversion->commission_amount + (int) ($conversion->flash_sale_bonus_amount ?? 0);
        $this->assertSame($totalCommission, $wallet->pending_balance);
        $this->assertSame(0, $wallet->balance);

        // Clearing window not passed yet — still pending_balance.
        (new ReleaseMarketerPendingCommissionJob())->handle();
        $wallet->refresh();
        $this->assertSame($totalCommission, $wallet->pending_balance);
        $this->assertSame(0, $wallet->balance);

        // After the clearing window, it moves into the spendable balance.
        $conversion->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob())->handle();

        $wallet->refresh();
        $conversion->refresh();
        $this->assertSame(0, $wallet->pending_balance);
        $this->assertSame($totalCommission, $wallet->balance);
        $this->assertNotNull($conversion->wallet_released_at);
    }

    public function test_cancelling_the_order_reverses_the_conversion_and_wallet_credit(): void
    {
        $scenario = $this->buildScenario();
        $order = $this->placeMarketerListingOrder($scenario);

        $this->deliverAllSubOrders($order);

        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        (new ApproveMarketerConversionsJob())->handle();

        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->first();
        $this->assertSame('approved', $conversion->status);

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $scenario->marketer->id)->first();
        $creditedAmount = $wallet->pending_balance;
        $this->assertGreaterThan(0, $creditedAmount);

        app(OrderCancellationService::class)->cancel($order->fresh(), CancelActor::Admin, 'Test reversal', force: true);

        $conversion->refresh();
        $this->assertSame('reversed', $conversion->status);
        $this->assertNotNull($conversion->reversed_at);

        $wallet->refresh();
        $this->assertSame(0, $wallet->pending_balance);
    }

    public function test_campaign_auto_pauses_when_commission_budget_is_exhausted(): void
    {
        $scenario = $this->buildScenario();
        $scenario->marketerCampaign->update(['max_commission_budget' => $scenario->marketerCampaign->marketer_commission_amount]);

        $order = $this->placeMarketerListingOrder($scenario);
        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);
        $this->assertNotNull(MarketerCampaignConversion::where('order_item_id', $item->id)->first());

        $scenario->marketerCampaign->refresh();
        $this->assertSame('paused', $scenario->marketerCampaign->status);
        $this->assertGreaterThanOrEqual(
            $scenario->marketerCampaign->max_commission_budget,
            $scenario->marketerCampaign->commission_budget_spent
        );
    }
}
