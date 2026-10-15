<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Models\CartItem;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\Order;
use App\Models\Wallet;
use App\Services\LastClickAttributionService;
use App\Services\OrderCancellationService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketerV3Fixtures;
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
        app()->call([new ApproveMarketerConversionsJob(), 'handle']);
        $conversion->refresh();
        $this->assertSame('pending', $conversion->status);

        // Move the return window into the past and re-run the job.
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        app()->call([new ApproveMarketerConversionsJob(), 'handle']);

        $conversion->refresh();
        $this->assertSame('approved', $conversion->status);
        $this->assertNotNull($conversion->wallet_credited_at);

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $scenario->marketer->id)->first();
        $this->assertNotNull($wallet);
        $totalCommission = (float) $conversion->net_commission_amount;
        $this->assertEquals($totalCommission, $wallet->pending_balance);
        $this->assertSame(0, $wallet->balance);

        // Clearing window not passed yet — still pending_balance.
        (new ReleaseMarketerPendingCommissionJob())->handle();
        $wallet->refresh();
        $this->assertEquals($totalCommission, $wallet->pending_balance);
        $this->assertSame(0, $wallet->balance);

        // After the clearing window, it moves into the spendable balance.
        $conversion->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob())->handle();

        $wallet->refresh();
        $conversion->refresh();
        $this->assertSame(0, $wallet->pending_balance);
        $this->assertEquals($totalCommission, $wallet->balance);
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

        app()->call([new ApproveMarketerConversionsJob(), 'handle']);

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

    public function test_the_conversion_snapshots_gross_fee_and_net_and_the_wallet_gets_the_net(): void
    {
        $scenario = $this->buildScenario();
        MarketerV3Fixtures::defaults($scenario->country, 'product', feePct: 10, feeFixed: 2);

        $order = $this->placeMarketerListingOrder($scenario);
        $this->deliverAllSubOrders($order);
        $item = $order->fresh('items')->items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);
        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();

        $this->assertSame('product_sale', $conversion->conversion_type);
        $this->assertEquals(round((float) $conversion->sale_amount * 0.10, 2), $conversion->gross_commission_amount);
        $this->assertEquals(round($conversion->gross_commission_amount * 0.10 + 2, 2), $conversion->commission_fee_amount);
        $this->assertEquals(round($conversion->gross_commission_amount - $conversion->commission_fee_amount, 2), $conversion->net_commission_amount);

        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        app()->call([new ApproveMarketerConversionsJob(), 'handle']);

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $scenario->marketer->id)->firstOrFail();
        $this->assertEquals($conversion->fresh()->net_commission_amount, $wallet->pending_balance);
    }

    public function test_an_order_is_converted_once_even_if_attribution_runs_again(): void
    {
        $scenario = $this->buildScenario();
        $order = $this->placeMarketerListingOrder($scenario);

        app(LastClickAttributionService::class)->resolveAndRecordConversion($order->fresh(), null);

        $this->assertSame(1, MarketerCampaignConversion::where('order_id', $order->id)->count());
        $this->assertEquals(
            MarketerCampaignConversion::where('order_id', $order->id)->value('commission_amount'),
            $scenario->marketerCampaign->fresh()->commission_budget_spent
        );
    }

    public function test_an_influencer_gets_no_per_sale_conversion(): void
    {
        $scenario = $this->buildScenario();
        $job = MarketerJob::firstOrCreate(['key' => 'influencer'], ['name_ar' => 'influencer', 'name_en' => 'influencer', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $scenario->marketer->id, 'marketer_job_id' => $job->id]);

        $order = $this->placeMarketerListingOrder($scenario);

        $this->assertSame(0, MarketerCampaignConversion::where('order_id', $order->id)->count());
        $this->assertEquals(0, $scenario->marketerCampaign->fresh()->commission_budget_spent);
    }

    public function test_campaign_is_done_when_commission_budget_is_exhausted_and_the_last_commission_is_capped(): void
    {
        $scenario = $this->buildScenario();
        // The affiliate's 10 % rule would pay about 100; only 40 of the budget is left.
        $scenario->marketerCampaign->update(['max_commission_budget' => 40]);

        $order = $this->placeMarketerListingOrder($scenario);
        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);
        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->first();
        $this->assertNotNull($conversion);
        $this->assertEquals(40, $conversion->commission_amount);

        $scenario->marketerCampaign->refresh();
        $this->assertSame('done', $scenario->marketerCampaign->status);
        $this->assertEquals(40, $scenario->marketerCampaign->commission_budget_spent);
    }
}
