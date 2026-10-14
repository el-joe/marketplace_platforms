<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Enums\MarketingFeeType;
use App\Enums\VendorGlobalStatus;
use App\Jobs\CollectPendingInfluencerFeesJob;
use App\Jobs\GenerateVendorPayoutsJob;
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
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Customer\CartService;
use App\Services\MarketerCampaignService;
use App\Services\MarketerFeePayoutService;
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
 * P5: the vendor is charged fee + VAT + its marketing-fee share on accept (wallet, else statement), all accepted
 * invitations sum to the campaign snapshot (22.69), and the influencer's fee share (20% of 9.00 = 1.80) is
 * credited to the marketer wallet exactly once on every paid path.
 */
class InfluencerChargeAndMarketerCreditTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaignService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
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

    public function test_wallet_path_debits_fee_vat_and_marketing_and_all_accepts_sum_to_the_snapshot(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $this->assertEqualsWithDelta(22.69, (float) $campaign->campaign_grand_total, 0.0001);
        $this->topUp(100);

        $a = $this->invitationFor($campaign, $marketers[0]);
        $b = $this->invitationFor($campaign, $marketers[1]);
        $this->svc->acceptInvitation($a);
        $this->svc->acceptInvitation($b);
        $a->refresh();
        $b->refresh();

        $this->assertEqualsWithDelta(1.90, (float) $a->platform_fee_marketing_amount, 0.0001);
        $this->assertEqualsWithDelta(1.89, (float) $b->platform_fee_marketing_amount, 0.0001);
        $this->assertEqualsWithDelta(11.35, $a->platformFeeTotal(), 0.0001);
        $this->assertEqualsWithDelta(11.34, $b->platformFeeTotal(), 0.0001);
        $this->assertEqualsWithDelta(22.69, $a->platformFeeTotal() + $b->platformFeeTotal(), 0.0001);
        $this->assertEqualsWithDelta(
            (float) $campaign->marketing_fee_total,
            (float) $a->platform_fee_marketing_amount + (float) $b->platform_fee_marketing_amount,
            0.0001
        );
        $this->assertEqualsWithDelta(100 - 22.69, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame('paid', $a->platform_fee_status);
        $this->assertSame('wallet', $b->platform_fee_paid_via);

        foreach ([[$a, $marketers[0]], [$b, $marketers[1]]] as [$inv, $m]) {
            $this->assertEqualsWithDelta(20.0, (float) $inv->marketer_fee_share_pct, 0.0001);
            $this->assertEqualsWithDelta(1.80, (float) $inv->marketer_payout_amount, 0.0001);
            $this->assertNotNull($inv->marketer_credited_at);
            $this->assertNotNull($inv->marketer_payout_wallet_tx_id);
            $this->assertEqualsWithDelta(1.80, (float) $this->marketerWallet($m)->balance, 0.0001);
            $this->assertSame(1, $this->creditCount($m));
        }
    }

    public function test_a_single_accepted_invitation_still_takes_its_share_and_the_last_one_takes_the_remainder(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $this->topUp(100);

        // Acceptance order is irrelevant: whoever accepts last gets the rounding remainder.
        $this->svc->acceptInvitation($this->invitationFor($campaign, $marketers[1]));
        $first = $this->invitationFor($campaign, $marketers[1]);
        $this->assertEqualsWithDelta(1.90, (float) $first->platform_fee_marketing_amount, 0.0001);

        $this->svc->acceptInvitation($this->invitationFor($campaign, $marketers[0]));
        $this->assertEqualsWithDelta(1.89, (float) $this->invitationFor($campaign, $marketers[0])->platform_fee_marketing_amount, 0.0001);
    }

    public function test_insufficient_wallet_leaves_it_pending_then_the_job_collects_and_credits_once(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $this->topUp(11.34); // one cent short of the first charge (11.35)

        $a = $this->invitationFor($campaign, $marketers[0]);
        $this->svc->acceptInvitation($a);
        $a->refresh();

        $this->assertSame('pending', $a->platform_fee_status);
        $this->assertEqualsWithDelta(11.34, (float) $this->vendorWallet()->fresh()->balance, 0.0001, 'nothing debited while short');
        $this->assertNull($a->marketer_credited_at);
        $this->assertSame(0, $this->creditCount($marketers[0]));

        $this->topUp(0.01);
        (new CollectPendingInfluencerFeesJob)->handle($this->svc);
        (new CollectPendingInfluencerFeesJob)->handle($this->svc);

        $a->refresh();
        $this->assertSame('paid', $a->platform_fee_status);
        $this->assertEqualsWithDelta(0.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(1, $this->creditCount($marketers[0]));
        $this->assertEqualsWithDelta(1.80, (float) $this->marketerWallet($marketers[0])->balance, 0.0001);
    }

    public function test_decline_and_timeout_charge_and_credit_nothing(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign();
        $this->topUp(100);

        $this->svc->rejectInvitation($this->invitationFor($campaign, $marketers[0]), 'no');
        $b = $this->invitationFor($campaign, $marketers[1]);
        $b->update(['expires_at' => now()->subMinute()]);
        $this->svc->handleInvitationTimeout($b->id);

        $this->assertEqualsWithDelta(100.0, (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertSame(0, WalletTransaction::where('source_type', 'marketer_campaign_fee_share')->count());
        foreach ($campaign->invitations()->get() as $inv) {
            $this->assertEqualsWithDelta(0.0, (float) $inv->platform_fee_marketing_amount, 0.0001);
            $this->assertEqualsWithDelta(0.0, (float) $inv->marketer_payout_amount, 0.0001);
            $this->assertNull($inv->marketer_credited_at);
        }
    }

    public function test_frozen_marketer_wallet_does_not_break_the_vendor_charge_and_the_retry_job_credits_once(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign(1);
        $this->topUp(100);
        $m = $marketers[0];
        $this->marketerWallet($m)->update(['is_frozen' => true, 'frozen_reason' => 'review']);

        $inv = $this->invitationFor($campaign, $m);
        $this->svc->acceptInvitation($inv);
        $inv->refresh();

        $this->assertSame('paid', $inv->platform_fee_status, 'vendor charge stands');
        $this->assertEqualsWithDelta(100 - $inv->platformFeeTotal(), (float) $this->vendorWallet()->fresh()->balance, 0.0001);
        $this->assertNull($inv->marketer_credited_at);
        $this->assertNull($inv->marketer_payout_wallet_tx_id);
        $this->assertSame(0, $this->creditCount($m));

        (new RetryMarketerFeeCreditsJob)->handle(app(MarketerFeePayoutService::class));
        $this->assertSame(0, $this->creditCount($m), 'still frozen');

        $this->marketerWallet($m)->update(['is_frozen' => false, 'frozen_reason' => null]);
        (new RetryMarketerFeeCreditsJob)->handle(app(MarketerFeePayoutService::class));
        (new RetryMarketerFeeCreditsJob)->handle(app(MarketerFeePayoutService::class));

        $inv->refresh();
        $this->assertNotNull($inv->marketer_credited_at);
        $this->assertSame(1, $this->creditCount($m));
        $this->assertEqualsWithDelta(9 * 0.20, (float) $this->marketerWallet($m)->balance, 0.0001);
        $tx = WalletTransaction::findOrFail($inv->marketer_payout_wallet_tx_id);
        $this->assertSame('marketer_campaign_fee_share', $tx->source_type);
        $this->assertSame($inv->id, $tx->source_id);
    }

    public function test_credit_is_idempotent_and_skips_unpaid_zero_and_affiliate_invitations(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign(1);
        $inv = $this->invitationFor($campaign, $marketers[0]);
        $service = app(MarketerFeePayoutService::class);

        $this->svc->acceptInvitation($inv); // empty wallet -> pending
        $this->assertFalse($service->creditForInvitation($inv->fresh()), 'charge not paid yet');

        $this->topUp(100);
        $this->assertTrue($this->svc->collectInfluencerFee($inv->fresh()));
        $this->assertFalse($service->creditForInvitation($inv->fresh()), 'already credited by the paid hook');
        $this->assertSame(1, $this->creditCount($marketers[0]));

        // Zero share: no payout, no credit.
        MarketerCommissionCountrySetting::where('category_id', $this->s->category->id)->update(['influencer_fee_share_pct' => 0]);
        [$c2, $m2] = $this->approvedCampaign(1);
        $this->svc->acceptInvitation($this->invitationFor($c2, $m2[0]));
        $zero = $this->invitationFor($c2, $m2[0]);
        $this->assertEqualsWithDelta(0.0, (float) $zero->marketer_payout_amount, 0.0001);
        $this->assertSame('paid', $zero->platform_fee_status);
        $this->assertSame(0, $this->creditCount($m2[0]));

        // Affiliates are never credited, even if a payout were forced onto the row.
        $aff = F::affiliate($this->s);
        $c3 = F::campaign($this->s, [$aff]);
        $this->svc->approveCampaign($c3, Admin::factory()->create());
        $affInv = $this->invitationFor($c3->fresh(), $aff);
        $this->svc->acceptInvitation($affInv);
        $affInv->refresh();
        $this->assertSame('not_applicable', $affInv->platform_fee_status);
        $affInv->update(['platform_fee_status' => 'paid', 'platform_fee_payer' => 'vendor', 'marketer_payout_amount' => 5]);
        $this->assertFalse($service->creditForInvitation($affInv->fresh()));
        $this->assertSame(0, $this->creditCount($aff));
    }

    public function test_statement_path_charges_the_same_total_and_credits_the_marketer_once(): void
    {
        $subOrder = $this->eligibleSale();
        [$campaign, $marketers] = $this->approvedCampaign();
        foreach ($marketers as $m) {
            $this->svc->acceptInvitation($this->invitationFor($campaign, $m));
        }
        $a = $this->invitationFor($campaign, $marketers[0]);
        $b = $this->invitationFor($campaign, $marketers[1]);
        $this->assertSame('pending', $a->platform_fee_status);
        $this->assertSame(0, $this->creditCount($marketers[0]));

        (new GenerateVendorPayoutsJob(now()->subDays(7), now()))->handle(app(PayoutCalculationService::class));

        $lineA = PayoutItem::where('invitation_id', $a->id)->sole();
        $lineB = PayoutItem::where('invitation_id', $b->id)->sole();
        $this->assertEqualsWithDelta(-11.35, (float) $lineA->net, 0.0001);
        $this->assertEqualsWithDelta(-11.34, (float) $lineB->net, 0.0001);
        $this->assertEqualsWithDelta(0.45, (float) $lineA->vat_amount, 0.0001);
        $this->assertEqualsWithDelta(1.90, (float) $lineA->marketing_fee_amount, 0.0001);
        $this->assertEqualsWithDelta(1.89, (float) $lineB->marketing_fee_amount, 0.0001);
        $this->assertEqualsWithDelta(9.0, abs((float) $lineA->net) - (float) $lineA->vat_amount - (float) $lineA->marketing_fee_amount, 0.0001);
        $this->assertStringContainsString('Marketing fees', $lineA->description);

        $payout = $lineA->payout;
        $this->assertEqualsWithDelta((float) $subOrder->vendor_payout - 22.69, (float) $payout->net_amount, 0.0001);
        $this->assertEqualsWithDelta(-22.69, (float) $payout->other_adjustments, 0.0001);

        foreach ($marketers as $m) {
            $this->assertSame(1, $this->creditCount($m));
            $this->assertEqualsWithDelta(1.80, (float) $this->marketerWallet($m)->balance, 0.0001);
        }
        $this->assertSame('statement', $a->fresh()->platform_fee_paid_via);

        // Re-running the job and the retry job never credits again.
        (new GenerateVendorPayoutsJob(now()->subDays(7), now()))->handle(app(PayoutCalculationService::class));
        (new RetryMarketerFeeCreditsJob)->handle(app(MarketerFeePayoutService::class));
        $this->assertSame(1, $this->creditCount($marketers[0]));
        $this->assertSame(1, $this->creditCount($marketers[1]));
    }

    public function test_admin_marking_the_charge_paid_credits_the_marketer_once(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign(1);
        $inv = $this->invitationFor($campaign, $marketers[0]);
        $this->svc->acceptInvitation($inv);
        $this->assertSame(0, $this->creditCount($marketers[0]));

        $admin = Admin::factory()->create();
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach (['marketer_campaigns.view', 'marketer_campaigns.approve'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
            $admin->givePermissionTo($name);
        }
        $url = route('admin.marketer-campaigns.invitations.mark-fee-paid', [$campaign, $inv]);

        $this->withoutVite()->actingAs($admin, 'admin')->patch($url)->assertRedirect();
        $this->actingAs($admin, 'admin')->patch($url)->assertStatus(422);

        $this->assertSame(1, $this->creditCount($marketers[0]));
    }

    public function test_vendor_transaction_feed_shows_the_full_charge_and_the_marketing_part(): void
    {
        [$campaign, $marketers] = $this->approvedCampaign(1);
        $this->topUp(100);
        $this->svc->acceptInvitation($this->invitationFor($campaign, $marketers[0]));

        $row = app(TransactionFeedService::class)->getFeed($this->s->vendor, 'influencer_fee', null, null, 1, 50)['items'][0];

        $this->assertEqualsWithDelta(-12.30, (float) $row['net'], 0.0001);
        $this->assertEqualsWithDelta(2.85, (float) $row['marketing_fee'], 0.0001);
        $this->assertStringContainsString('marketing fees', $row['description']);
        $this->assertEqualsWithDelta(9.0, (float) $row['fee'], 0.0001);
        $this->assertEqualsWithDelta(0.45, (float) $row['vat'], 0.0001);
    }

    public function test_vendor_and_admin_pages_show_the_marketing_part_and_the_full_charge(): void
    {
        $this->withoutVite();
        [$campaign, $marketers] = $this->approvedCampaign(1);
        $inv = $this->invitationFor($campaign, $marketers[0]);
        $this->svc->acceptInvitation($inv); // pending: 9.00 + 0.45 + 2.85 = 12.30

        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit', 'marketer_campaigns.delete', 'finance.view', 'finance.payouts.view'] as $name) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.finance.transactions', ['type' => 'influencer_fees', 'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('12.30')
            ->assertSee(__('partner.finance.marketing_fees_label'));

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="invitation-fee-marketing"', false)
            ->assertSee(__('partner.marketer_campaigns.fee_summary_with_marketing', ['fee' => '9', 'vat' => '0.45', 'marketing' => '2.85', 'total' => '12.30', 'currency' => 'AED']));

        $payout = Payout::create([
            'payout_number' => 'PO-2026-'.random_int(10000, 99999), 'vendor_id' => $this->s->vendor->id,
            'period_start' => now()->subWeek()->toDateString(), 'period_end' => now()->toDateString(),
            'gross_sales' => 50, 'commission' => 0, 'refunds_deducted' => 0, 'chargebacks_deducted' => 0,
            'other_adjustments' => -12.30, 'net_amount' => 37.70, 'currency' => 'AED', 'status' => 'pending',
            'bank_account_id' => VendorBankAccount::first()->id, 'payout_method' => 'bank_transfer',
        ]);
        PayoutItem::create([
            'payout_id' => $payout->id, 'item_type' => 'influencer_fee', 'invitation_id' => $inv->id,
            'gross' => 0, 'commission' => 0, 'vat_amount' => 0.45, 'marketing_fee_amount' => 2.85, 'net' => -12.30,
            'description' => 'Influencer fee',
        ]);

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.payouts.show', $payout->payout_number))
            ->assertOk()
            ->assertSee(__('partner.payouts.marketing_fees'))
            ->assertSee('2.85')
            ->assertSee('12.30');

        $admin = Admin::factory()->create();
        foreach (['vendors.assigned_only', 'marketer_campaigns.view', 'payouts.view'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo(['marketer_campaigns.view', 'payouts.view']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.show', $payout->id))
            ->assertOk()
            ->assertSee(__('admin.payouts.influencer_fee_vat_marketing_note', ['fee' => 'AED 9.00', 'vat' => 'AED 0.45', 'marketing' => 'AED 2.85']));
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="admin-invitation-marketing"', false)
            ->assertSee('data-testid="admin-invitation-payout"', false);
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
