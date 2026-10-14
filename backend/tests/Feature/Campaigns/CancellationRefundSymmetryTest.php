<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Enums\MarketingFeeType;
use App\Enums\VendorGlobalStatus;
use App\Jobs\CollectPendingInfluencerFeesJob;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Jobs\RetryInvitationFeeRefundsJob;
use App\Jobs\RetryMarketerFeeCreditsJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\Order;
use App\Models\PayoutItem;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Customer\CartService;
use App\Services\MarketerCampaignService;
use App\Services\MarketerFeePayoutService;
use App\Services\MarketerInvitationRefundService;
use App\Services\OrderStateMachine;
use App\Services\PayoutCalculationService;
use App\Services\Vendor\TransactionFeedService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P7: cancelling / rejecting a campaign refunds the vendor what accepted influencers were actually charged
 * (22.69 in the plan example), voids unpaid fees and reverses the marketer's fee-share credit, idempotently.
 */
class CancellationRefundSymmetryTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaignService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        Permission::firstOrCreate(['name' => 'payouts.approve', 'guard_name' => 'admin']);
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->s->vendor->update(['global_status' => VendorGlobalStatus::Active]);
        VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => $this->s->country->currency_code,
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        $this->svc = app(MarketerCampaignService::class);
        F::setting('marketer_influencer_fee_payer', 'vendor');

        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 9, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
        MarketingFeeCountrySetting::updateOrCreate(['country_id' => $this->s->country->id], ['fixed_amount' => 1.9, 'fixed_percentage' => 0]);
        MarketingFeeCategorySetting::updateOrCreate(
            ['country_id' => $this->s->country->id, 'category_id' => $this->s->category->id, 'fulfillment_type' => MarketingFeeType::Fbn->value],
            ['fixed_amount' => 0, 'percentage' => 10]
        );
        MarketerCommissionCountrySetting::updateOrCreate(
            ['category_id' => $this->s->category->id, 'country_id' => $this->s->country->id],
            ['currency' => 'AED', 'influencer_fee_share_pct' => 20]
        );
    }

    private function vendorWallet(): Wallet
    {
        return app(WalletService::class)->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
    }

    private function marketerWallet(Marketer $m): Wallet
    {
        return app(WalletService::class)->getOrCreateWallet('marketer', $m->id, 'AED');
    }

    private function topUp(float $amount): void
    {
        app(WalletService::class)->credit($this->vendorWallet(), $amount, 'test', null, 'topup');
    }

    /** @return array{0: MarketerCampaign, 1: array<int, Marketer>} approved campaign for two influencers */
    private function approvedCampaign(int $influencers = 2): array
    {
        Queue::fake();
        $marketers = [];
        for ($i = 0; $i < $influencers; $i++) {
            $marketers[] = F::influencer($this->s);
        }
        $campaign = F::campaign($this->s, $marketers);
        $this->svc->approveCampaign($campaign, Admin::factory()->create());

        return [$campaign->fresh(), $marketers];
    }

    private function invitationFor(MarketerCampaign $campaign, Marketer $m): MarketerCampaignInvitation
    {
        return $campaign->invitations()->where('marketer_id', $m->id)->sole();
    }

    private function creditCount(Marketer $m): int
    {
        return WalletTransaction::where('wallet_id', $this->marketerWallet($m)->id)
            ->where('source_type', 'marketer_campaign_fee_share')->count();
    }

    private function refundCount(): int
    {
        return WalletTransaction::where('wallet_id', $this->vendorWallet()->id)
            ->where('source_type', MarketerInvitationRefundService::REFUND_SOURCE)->count();
    }

    private function reversalCount(Marketer $m): int
    {
        return WalletTransaction::where('wallet_id', $this->marketerWallet($m)->id)
            ->where('source_type', MarketerInvitationRefundService::REVERSAL_SOURCE)->count();
    }

    /** @return array{0: MarketerCampaign, 1: array<int, Marketer>} both influencers accepted and paid from the wallet */
    private function paidCampaign(): array
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $this->topUp(100);
        foreach ($marketers as $m) {
            $this->svc->acceptInvitation($this->invitationFor($campaign, $m));
        }

        return [$campaign, $marketers];
    }

    public function test_cancelling_refunds_the_full_charge_and_reverses_the_marketer_credit(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();
        $this->assertEqualsWithDelta(100 - 22.69, (float) $this->vendorWallet()->fresh()->balance, 0.0001);

        $this->svc->cancelCampaign($campaign->fresh());

        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(2, $this->refundCount());
        $a = $this->invitationFor($campaign, $marketers[0]);
        $b = $this->invitationFor($campaign, $marketers[1]);
        $this->assertSame('refunded', $a->platform_fee_status);
        $this->assertEqualsWithDelta(11.35, (float) $a->platform_fee_refund_amount, 0.0001);
        $this->assertEqualsWithDelta(11.34, (float) $b->platform_fee_refund_amount, 0.0001);
        $tx = WalletTransaction::findOrFail($a->platform_fee_refund_wallet_tx_id);
        $this->assertSame('marketer_campaign_invitation_platform_fee_refund', $tx->source_type);
        $this->assertSame($a->id, $tx->source_id);

        foreach ([[$a, $marketers[0]], [$b, $marketers[1]]] as [$inv, $m]) {
            $this->assertNotNull($inv->marketer_credit_reversed_at);
            $this->assertNotNull($inv->marketer_credit_reversal_wallet_tx_id);
            $this->assertEqualsWithDelta(0.0, (float) $inv->marketer_clawback_amount, 0.0001);
            $this->assertEqualsWithDelta(0.0, (float) $this->marketerWallet($m)->balance, 0.0001);
            $this->assertSame(1, $this->creditCount($m));
            $this->assertSame(1, $this->reversalCount($m));
        }
    }

    public function test_double_cancel_and_the_safety_net_never_refund_or_reverse_twice(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();

        $this->svc->cancelCampaign($campaign->fresh());
        $this->svc->cancelCampaign($campaign->fresh());
        app(MarketerInvitationRefundService::class)->refundCampaign($campaign->fresh());
        (new RetryInvitationFeeRefundsJob)->handle(app(MarketerInvitationRefundService::class));
        (new RetryMarketerFeeCreditsJob)->handle(app(MarketerFeePayoutService::class));

        $this->assertSame(2, $this->refundCount());
        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        foreach ($marketers as $m) {
            $this->assertSame(1, $this->reversalCount($m));
            $this->assertSame(1, $this->creditCount($m));
            $this->assertEqualsWithDelta(0.0, (float) $this->marketerWallet($m)->balance, 0.0001);
        }
    }

    public function test_short_marketer_wallet_records_a_clawback_without_blocking_the_vendor_refund(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();
        $m = $marketers[0];
        // The marketer already withdrew part of the 1.80 credit.
        $this->marketerWallet($m)->update(['balance' => 0.50]);

        $this->svc->cancelCampaign($campaign->fresh());

        $inv = $this->invitationFor($campaign, $m);
        $this->assertSame('refunded', $inv->platform_fee_status, 'vendor refund unaffected');
        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertNull($inv->marketer_credit_reversed_at);
        $this->assertEqualsWithDelta(1.80, (float) $inv->marketer_clawback_amount, 0.0001);
        $this->assertTrue($inv->hasPendingClawback());
        $this->assertEqualsWithDelta(0.50, (float) $this->marketerWallet($m)->balance, 0.0001, 'nothing taken while short');
        $this->assertSame(0, $this->reversalCount($m));

        // Still short: the hourly job leaves it pending.
        (new RetryInvitationFeeRefundsJob)->handle(app(MarketerInvitationRefundService::class));
        $this->assertTrue($inv->fresh()->hasPendingClawback());

        // Funded later: recovered once.
        app(WalletService::class)->credit($this->marketerWallet($m), 5, 'test', null, 'topup');
        (new RetryInvitationFeeRefundsJob)->handle(app(MarketerInvitationRefundService::class));
        (new RetryInvitationFeeRefundsJob)->handle(app(MarketerInvitationRefundService::class));

        $inv->refresh();
        $this->assertNotNull($inv->marketer_credit_reversed_at);
        $this->assertEqualsWithDelta(0.0, (float) $inv->marketer_clawback_amount, 0.0001);
        $this->assertSame(1, $this->reversalCount($m));
        $this->assertEqualsWithDelta(0.50 + 5 - 1.80, (float) $this->marketerWallet($m)->balance, 0.0001);
    }

    public function test_a_frozen_marketer_wallet_also_becomes_a_clawback(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();
        $this->marketerWallet($marketers[1])->update(['is_frozen' => true, 'frozen_reason' => 'review']);

        $this->svc->cancelCampaign($campaign->fresh());

        $inv = $this->invitationFor($campaign, $marketers[1]);
        $this->assertTrue($inv->hasPendingClawback());
        $this->assertSame(1, $this->refundCount() - 1);
        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
    }

    public function test_an_unpaid_fee_is_voided_and_never_collected_or_credited(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $a = $this->invitationFor($campaign, $marketers[0]);
        $this->svc->acceptInvitation($a); // empty wallet -> pending
        $this->assertSame('pending', $a->fresh()->platform_fee_status);

        $this->svc->cancelCampaign($campaign->fresh());

        $a->refresh();
        $this->assertSame('waived', $a->platform_fee_status);
        $this->assertNotNull($a->platform_fee_voided_at);
        $this->assertNull($a->platform_fee_refunded_at);
        $this->assertSame(0, $this->refundCount());
        $this->assertNotNull($a->marketer_credit_reversed_at, 'nothing to take back, marked closed');

        $this->topUp(100);
        (new CollectPendingInfluencerFeesJob)->handle($this->svc);
        $this->assertFalse($this->svc->collectInfluencerFee($a->fresh()));
        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(0, $this->creditCount($marketers[0]));
        $this->assertFalse(app(MarketerFeePayoutService::class)->creditForInvitation($a->fresh()));
    }

    public function test_a_statement_paid_fee_is_refunded_through_the_wallet_and_the_statement_line_stays(): void
    {
        $this->eligibleSale();
        [$campaign, $marketers] = $this->approvedCampaign();
        foreach ($marketers as $m) {
            $this->svc->acceptInvitation($this->invitationFor($campaign, $m));
        }
        (new GenerateVendorPayoutsJob(now()->subDays(7), now()))->handle(app(PayoutCalculationService::class));
        $a = $this->invitationFor($campaign, $marketers[0]);
        $this->assertSame('statement', $a->platform_fee_paid_via);
        $walletBefore = (float) $this->vendorWallet()->fresh()->balance;

        $this->svc->cancelCampaign($campaign->fresh());

        $a->refresh();
        $this->assertSame('refunded', $a->platform_fee_status);
        $this->assertSame('statement', $a->platform_fee_paid_via, 'history of how it was paid is kept');
        $this->assertEqualsWithDelta($walletBefore + 22.69, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(1, PayoutItem::where('invitation_id', $a->id)->count(), 'issued statement is never edited');
        $this->assertSame(1, $this->reversalCount($marketers[0]));
        $this->assertEqualsWithDelta(0.0, (float) $this->marketerWallet($marketers[0])->balance, 0.0001);
    }

    public function test_rejecting_a_campaign_refunds_too(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();

        $this->svc->rejectCampaign($campaign->fresh(), Admin::factory()->create(), 'policy');

        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(2, $this->refundCount());
        $this->assertSame('campaign_rejected', $this->invitationFor($campaign, $marketers[0])->platform_fee_refund_reason);
    }

    public function test_a_frozen_vendor_wallet_defers_the_refund_to_the_hourly_job(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();
        $this->vendorWallet()->update(['is_frozen' => true, 'frozen_reason' => 'audit']);

        $this->svc->cancelCampaign($campaign->fresh());

        $a = $this->invitationFor($campaign, $marketers[0]);
        $this->assertSame('paid', $a->platform_fee_status);
        $this->assertNull($a->platform_fee_refunded_at);
        $this->assertNull($a->marketer_credit_reversed_at, 'marketer side waits for the vendor side');
        $this->assertSame(0, $this->refundCount());

        $this->vendorWallet()->update(['is_frozen' => false, 'frozen_reason' => null]);
        (new RetryInvitationFeeRefundsJob)->handle(app(MarketerInvitationRefundService::class));
        (new RetryInvitationFeeRefundsJob)->handle(app(MarketerInvitationRefundService::class));

        $this->assertSame(2, $this->refundCount());
        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(1, $this->reversalCount($marketers[0]));
    }

    public function test_a_credit_still_deferred_when_the_campaign_is_cancelled_is_never_made(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign(1);
        $this->topUp(100);
        $m = $marketers[0];
        $this->marketerWallet($m)->update(['is_frozen' => true, 'frozen_reason' => 'review']);
        $this->svc->acceptInvitation($this->invitationFor($campaign, $m));
        $this->assertSame(0, $this->creditCount($m));

        $this->svc->cancelCampaign($campaign->fresh());
        $this->marketerWallet($m)->update(['is_frozen' => false, 'frozen_reason' => null]);
        (new RetryMarketerFeeCreditsJob)->handle(app(MarketerFeePayoutService::class));

        $this->assertSame(0, $this->creditCount($m));
        $this->assertSame(0, $this->reversalCount($m));
        $this->assertEqualsWithDelta(0.0, (float) $this->marketerWallet($m)->balance, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
    }

    public function test_pending_and_declined_invitations_are_untouched_and_done_does_not_refund(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $this->topUp(100);
        $this->svc->acceptInvitation($this->invitationFor($campaign, $marketers[0]));
        $this->svc->markCampaignDone($campaign->fresh());

        $this->assertSame(0, $this->refundCount(), 'a finished campaign delivered its work');
        $this->assertSame('paid', $this->invitationFor($campaign, $marketers[0])->platform_fee_status);
    }

    public function test_refund_state_is_visible_in_vendor_admin_marketer_pages_and_the_finance_feed(): void
    {
        [$campaign, $marketers] = $this->paidCampaign();
        $this->marketerWallet($marketers[1])->update(['balance' => 0.10]);
        $this->svc->cancelCampaign($campaign->fresh());

        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit', 'marketer_campaigns.delete', 'marketer_campaigns.cancel', 'finance.view'] as $name) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
        $this->actingAs($vendorAdmin->fresh(), 'vendor')
            ->get(route('partner.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="invitation-fee-refunded"', false);

        $this->actingAs($vendorAdmin->fresh(), 'vendor')
            ->get(route('partner.finance.transactions', ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee(__('partner.finance.influencer_fee_refund'));

        $feed = app(TransactionFeedService::class)->getFeed($this->s->vendor, 'influencer_fee', null, null, 1, 50);
        $refunds = collect($feed['items'])->where('type', 'influencer_fee_refund');
        $this->assertCount(2, $refunds);
        $this->assertEqualsWithDelta(22.69, (float) $refunds->sum('net'), 0.0001);

        $perms = ['marketer_campaigns.view', 'marketer_campaigns.approve', 'vendors.assigned_only'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="admin-invitation-refunded-badge"', false)
            ->assertSee('data-testid="admin-invitation-reversal"', false)
            ->assertSee('data-testid="admin-invitation-clawback"', false);

        $this->actingAs(F::marketerLogin($marketers[0]), 'marketer')
            ->get(route('marketer.campaigns.show', $this->invitationFor($campaign, $marketers[0])->id))
            ->assertOk()
            ->assertSee('data-testid="marketer-fee-share-reversed"', false);

        $this->actingAs(F::marketerLogin($marketers[1]), 'marketer')
            ->get(route('marketer.campaigns.show', $this->invitationFor($campaign, $marketers[1])->id))
            ->assertOk()
            ->assertSee('data-testid="marketer-fee-share-clawback"', false);

        $this->actingAs(F::marketerLogin($marketers[1]), 'marketer')
            ->get(route('marketer.finance.wallet'))
            ->assertOk()
            ->assertSee('data-testid="wallet-clawbacks"', false);
    }

    private function eligibleSale()
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
}
