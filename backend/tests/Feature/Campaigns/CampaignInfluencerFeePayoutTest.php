<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Enums\VendorGlobalStatus;
use App\Jobs\CollectPendingInfluencerFeesJob;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use App\Services\Ads\AdBillingService;
use App\Services\Customer\CartService;
use App\Services\MarketerCampaignService;
use App\Services\OrderStateMachine;
use App\Services\PayoutCalculationService;
use App\Services\Vendor\TransactionFeedService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P9 (R2): a seller-paid influencer fee still `pending` at statement time is
 * deducted in the weekly statement as an `influencer_fee` line (fee + VAT) and the
 * invitation is marked paid - never both wallet and statement, never twice.
 */
class CampaignInfluencerFeePayoutTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Permission::firstOrCreate(['name' => 'payouts.approve', 'guard_name' => 'admin']);
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => $this->s->country->currency_code,
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        $this->s->vendor->update(['global_status' => VendorGlobalStatus::Active]);
        F::setting('marketer_influencer_fee_payer', 'vendor');
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 1000, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
    }

    private function eligibleSale(): SubOrder
    {
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)
            ->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $this->s->vendorListingFbp->id, 'quantity' => 2,
            'unit_price' => (int) $this->s->vendorListingFbp->getRawOriginal('price'), 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['wallet']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $response->assertStatus(201);
        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
        $subOrder = $order->subOrders()->where('vendor_id', $this->s->vendor->id)->firstOrFail();

        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }
        $subOrder->refresh()->items()->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        return $subOrder->refresh();
    }

    private function runPayouts(Carbon $from, Carbon $to, ?PayoutCalculationService $calc = null): void
    {
        (new GenerateVendorPayoutsJob($from, $to))->handle($calc ?? app(PayoutCalculationService::class));
    }

    /** Accepted influencer invitation whose fee is still pending (empty seller wallet). */
    private function pendingFeeInvitation(bool $affiliate = false): MarketerCampaignInvitation
    {
        Queue::fake();
        $svc = app(MarketerCampaignService::class);
        $campaign = F::campaign($this->s, [$affiliate ? F::affiliate($this->s) : F::influencer($this->s)]);
        $svc->approveCampaign($campaign, Admin::factory()->create());
        $inv = $campaign->invitations()->sole();
        $svc->acceptInvitation($inv);

        return $inv->fresh();
    }

    public function test_pending_fee_is_deducted_in_the_statement_with_vat_once_and_marked_paid(): void
    {
        $subOrder = $this->eligibleSale();
        $inv = $this->pendingFeeInvitation();
        $this->assertSame('pending', $inv->platform_fee_status);

        $this->runPayouts(now()->subDays(7), now());

        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $line = PayoutItem::where('invitation_id', $inv->id)->sole();
        $this->assertSame('influencer_fee', $line->item_type);
        $this->assertNull($line->sub_order_id);
        $this->assertEquals($payout->id, $line->payout_id);
        $this->assertSame(0, (int) $line->gross);
        $this->assertSame(50, (int) $line->vat_amount);
        $this->assertSame(-1050, (int) $line->net, 'fee 1000 + 5% VAT');
        $this->assertStringContainsString('Influencer fee', $line->description);

        $this->assertSame((int) $subOrder->vendor_payout - 1050, (int) $payout->net_amount);
        $this->assertSame(-1050, (int) $payout->other_adjustments);

        $inv->refresh();
        $this->assertSame('paid', $inv->platform_fee_status);
        $this->assertSame('statement', $inv->platform_fee_paid_via);

        // Later runs (new sale, overlapping window) and the wallet collector must not charge it again.
        $wallet = app(WalletService::class)->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
        app(WalletService::class)->credit($wallet, 9000, 'test', null, 'topup');
        (new CollectPendingInfluencerFeesJob)->handle(app(MarketerCampaignService::class));
        $this->assertSame(9000, $wallet->fresh()->balance, 'collector ignores a fee already on a statement');

        $this->eligibleSale();
        $this->runPayouts(now()->subDays(3), now()->addDay());
        $this->assertSame(1, PayoutItem::where('invitation_id', $inv->id)->count());
        $this->assertSame(1, PayoutItem::where('item_type', 'influencer_fee')->count());
    }

    public function test_a_fee_paid_from_the_wallet_during_the_week_is_not_on_the_statement(): void
    {
        $subOrder = $this->eligibleSale();
        $inv = $this->pendingFeeInvitation();
        $wallet = app(WalletService::class)->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
        app(WalletService::class)->credit($wallet, 5000, 'test', null, 'topup');
        (new CollectPendingInfluencerFeesJob)->handle(app(MarketerCampaignService::class));
        $this->assertSame('paid', $inv->fresh()->platform_fee_status);

        $this->runPayouts(now()->subDays(7), now());

        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $this->assertSame(0, PayoutItem::where('item_type', 'influencer_fee')->count());
        $this->assertSame((int) $subOrder->vendor_payout, (int) $payout->net_amount);
        $this->assertSame(5000 - 1050, $wallet->fresh()->balance);
    }

    public function test_affiliate_campaigns_add_no_fee_line(): void
    {
        $subOrder = $this->eligibleSale();
        $this->pendingFeeInvitation(affiliate: true);

        $this->runPayouts(now()->subDays(7), now());

        $this->assertSame(0, PayoutItem::where('item_type', 'influencer_fee')->count());
        $this->assertSame((int) $subOrder->vendor_payout, (int) Payout::where('vendor_id', $this->s->vendor->id)->sole()->net_amount);
    }

    public function test_fee_that_exceeds_the_weekly_net_rolls_forward(): void
    {
        $this->eligibleSale();
        $inv = $this->pendingFeeInvitation();
        $inv->update(['platform_fee_amount' => 900_000_000, 'platform_fee_vat_amount' => 45_000_000]);

        $this->runPayouts(now()->subDays(7), now());

        $this->assertGreaterThan(0, (int) Payout::where('vendor_id', $this->s->vendor->id)->sole()->net_amount);
        $this->assertSame(0, PayoutItem::where('invitation_id', $inv->id)->count());
        $this->assertSame('pending', $inv->fresh()->platform_fee_status);
    }

    public function test_fee_paid_by_the_wallet_between_calculation_and_write_is_not_charged_twice(): void
    {
        $subOrder = $this->eligibleSale();
        $inv = $this->pendingFeeInvitation();
        $test = $this;

        // The wallet collector wins the race right after the statement calculation selected the fee.
        $calc = new class(app(AdBillingService::class), $test) extends PayoutCalculationService
        {
            public function __construct(AdBillingService $ads, private $test)
            {
                parent::__construct($ads);
            }

            public function calculateForVendor(Vendor $vendor, Carbon $from, Carbon $to): array
            {
                $result = parent::calculateForVendor($vendor, $from, $to);
                $wallet = app(WalletService::class)->getOrCreateWallet('vendor', $vendor->id, 'AED');
                app(WalletService::class)->credit($wallet, 5000, 'test', null, 'topup');
                (new CollectPendingInfluencerFeesJob)->handle(app(MarketerCampaignService::class));

                return $result;
            }
        };

        $this->runPayouts(now()->subDays(7), now(), $calc);

        $this->assertSame('wallet', $inv->fresh()->platform_fee_paid_via);
        $this->assertSame(0, PayoutItem::where('item_type', 'influencer_fee')->count());
        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $this->assertSame((int) $subOrder->vendor_payout, (int) $payout->net_amount, 'seller is not charged on the statement as well');
        $this->assertSame(0, (int) $payout->other_adjustments);
    }

    public function test_transaction_feed_shows_fee_vat_and_where_it_was_settled(): void
    {
        $this->eligibleSale();
        $inv = $this->pendingFeeInvitation();
        $feed = app(TransactionFeedService::class);

        $rows = collect($feed->getFeed($this->s->vendor, 'influencer_fee', null, null, 1, 50)['items']);
        $this->assertCount(1, $rows);
        $this->assertSame(1000, $rows[0]['fee']);
        $this->assertSame(50, $rows[0]['vat']);
        $this->assertSame(-1050, $rows[0]['net']);
        $this->assertSame('unsettled', $rows[0]['payout_status']);

        Cache::flush();
        $this->runPayouts(now()->subDays(7), now());
        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $rows = collect($feed->getFeed($this->s->vendor, 'influencer_fee', null, null, 1, 50)['items']);
        $this->assertStringContainsString($payout->payout_number, $rows[0]['payout_status']);
        $this->assertSame('paid', $inv->fresh()->platform_fee_status);
    }
}
