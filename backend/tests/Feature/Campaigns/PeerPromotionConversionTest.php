<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Models\CartItem;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerListing;
use App\Models\Order;
use App\Models\Wallet;
use App\Services\Customer\CartService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PeerPromotionFixtures as P;
use Tests\TestCase;

/**
 * P13 (R11): a customer buys through the promoter's referral listing; stock and the seller are the
 * owner influencer's, the commission goes to the promoter by the category rules.
 */
class PeerPromotionConversionTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
    }

    private function order(MarketerListing $proxy, int $qty = 2): Order
    {
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)
            ->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $proxy->id, 'quantity' => $qty,
            'unit_price' => (int) $proxy->price, 'added_at' => now(),
        ]);

        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame(201, $response->status(), substr((string) $response->getContent(), 0, 600));

        return Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
    }

    public function test_order_through_the_promoters_listing_reserves_the_owners_stock_and_credits_the_promoter(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        P::promoterRule($this->s, $promoter, 3000);
        $ownerListing = P::ownListing($this->s, $owner, 20);
        $campaign = P::request($promoter, $ownerListing);
        $invitation = $campaign->invitations()->sole();
        $proxy = MarketerListing::where('invitation_id', $invitation->id)->sole();

        $order = $this->order($proxy, 2);
        $order->load('subOrders.items');
        $subOrder = $order->subOrders->sole();

        // The owner influencer is the seller of the sub-order.
        // D5 "owner pays": the owner influencer carries the promoter's commission.
        $this->assertSame('marketer', $subOrder->marketer_commission_owner);
        $this->assertSame(6000, (int) $subOrder->marketer_commission);
        $this->assertSame(
            (int) $subOrder->subtotal - (int) $subOrder->platform_commission - (int) $subOrder->commission_vat - 6000,
            (int) $subOrder->vendor_payout
        );
        $this->assertSame('marketer', $subOrder->seller_type);
        $this->assertSame($owner->id, $subOrder->marketer_id);
        $this->assertNull($subOrder->vendor_id);

        // The item references the promoter's listing; stock came from the owner's own inventory.
        $item = $subOrder->items->sole();
        $this->assertSame($proxy->id, $item->marketer_listing_id);
        $inventory = $ownerListing->warehouseInventories()->sole();
        $this->assertSame(2, (int) $inventory->quantity_reserved);

        // Attribution: commission for the promoter, per unit from the admin category setting.
        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->sole();
        $this->assertSame($invitation->id, $conversion->invitation_id);
        $this->assertSame($campaign->id, $conversion->campaign_id);
        $this->assertSame(6000, (int) $conversion->commission_amount);
        $this->assertSame($item->id, $item->fresh()->marketerConversion->order_item_id);
        $this->assertSame($invitation->id, $item->fresh()->marketer_campaign_invitation_id);
    }

    public function test_commission_is_credited_to_the_promoter_wallet_not_the_owner(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        P::promoterRule($this->s, $promoter, 3000);
        $campaign = P::request($promoter, P::ownListing($this->s, $owner, 20));
        $proxy = MarketerListing::where('invitation_id', $campaign->invitations()->sole()->id)->sole();

        $order = $this->order($proxy, 1);
        $machine = app(OrderStateMachine::class);
        $order->load('subOrders');
        foreach ($order->subOrders as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
        }
        $order->items()->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        $conversion = MarketerCampaignConversion::where('campaign_id', $campaign->id)->sole();
        $this->assertSame('approved', $conversion->status);
        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $promoter->id)->first();
        $this->assertNotNull($wallet);
        $this->assertGreaterThan(0, $wallet->pending_balance);
        $this->assertNull(Wallet::where('owner_type', 'marketer')->where('owner_id', $owner->id)->where('pending_balance', '>', 0)->first());
    }

    public function test_proxy_listing_cannot_oversell_the_owners_stock(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $campaign = P::request($promoter, P::ownListing($this->s, $owner, 3));
        $proxy = MarketerListing::where('invitation_id', $campaign->invitations()->sole()->id)->sole();

        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)
            ->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create(['cart_id' => $cart->id, 'marketer_listing_id' => $proxy->id, 'quantity' => 4, 'unit_price' => (int) $proxy->price, 'added_at' => now()]);

        $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422);
    }

    public function test_buying_the_owners_own_listing_directly_works_and_creates_no_conversion(): void
    {
        $owner = F::influencer($this->s);
        $ownerListing = P::ownListing($this->s, $owner, 10);
        P::request(F::influencer($this->s), $ownerListing);

        $order = $this->order($ownerListing, 1);
        $subOrder = $order->subOrders()->sole();

        $this->assertSame('marketer', $subOrder->seller_type);
        $this->assertSame($owner->id, $subOrder->marketer_id);
        $this->assertSame(1, (int) $ownerListing->warehouseInventories()->sole()->quantity_reserved);
        $this->assertSame(0, MarketerCampaignConversion::count());
    }
}
