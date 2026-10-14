<?php

namespace Tests\Feature\Campaigns;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\CollectPendingInfluencerFeesJob;
use App\Models\Admin;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\VendorBankAccount;
use App\Services\MarketerCampaignQuoteService;
use App\Services\MarketerCampaignService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P9 (R2), service + job: influencer fee = fee_per_influencer + country VAT
 * (countries.vat_rate, 5% in the scenario), taken from the seller wallet when it
 * can cover it, otherwise left pending for the job / the weekly statement.
 */
class CampaignInfluencerFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaignService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        $this->svc = app(MarketerCampaignService::class);
        F::setting('marketer_influencer_fee_payer', 'vendor');
        $this->setFee(1000);
    }

    private function setFee(int|float $fee): void
    {
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => $fee, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
    }

    private function wallet()
    {
        return app(WalletService::class)->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
    }

    /** Campaign for one influencer, approved (invitation dispatched) and accepted. */
    private function accepted(?int $topUp = null): MarketerCampaignInvitation
    {
        if ($topUp) {
            app(WalletService::class)->credit($this->wallet(), $topUp, 'test', null, 'topup');
        }
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $this->svc->approveCampaign($campaign, Admin::factory()->create());
        $inv = $campaign->invitations()->sole();
        $this->svc->acceptInvitation($inv);

        return $inv->fresh();
    }

    public function test_vat_comes_from_the_country_vat_rate(): void
    {
        $quote = app(MarketerCampaignQuoteService::class);
        $this->assertEquals(5.0, $quote->vatRate($this->s->country->id));
        $this->assertEquals(50, $quote->vatOn(1000, 5.0));
        $this->assertEquals(0, $quote->vatOn(1000, 0.0));

        $this->s->country->update(['vat_rate' => 14.00]);
        $this->assertEquals(14.0, $quote->vatRate($this->s->country->id));
        $this->assertEquals(0.0, $quote->vatRate(null));
    }

    public function test_wallet_covers_fee_and_vat_so_it_is_paid_immediately(): void
    {
        $inv = $this->accepted(topUp: 5000);

        $this->assertSame('paid', $inv->platform_fee_status);
        $this->assertSame('wallet', $inv->platform_fee_paid_via);
        $this->assertNotNull($inv->platform_fee_paid_at);
        $this->assertSame(1000, (int) $inv->platform_fee_amount);
        $this->assertSame(50, (int) $inv->platform_fee_vat_amount);
        $this->assertEquals(5.0, (float) $inv->platform_fee_vat_rate);
        $this->assertSame(5000 - 1050, $this->wallet()->fresh()->balance, 'fee + VAT in one debit');
        $this->assertSame(0, $inv->payoutItems()->count());
    }

    public function test_short_wallet_leaves_it_pending_and_the_job_pays_later_exactly_once(): void
    {
        $inv = $this->accepted(topUp: 1049); // one unit short of fee + VAT

        $this->assertSame('pending', $inv->platform_fee_status);
        $this->assertSame(1049, $this->wallet()->fresh()->balance, 'nothing debited while short');

        (new CollectPendingInfluencerFeesJob)->handle($this->svc);
        $this->assertSame('pending', $inv->fresh()->platform_fee_status);

        app(WalletService::class)->credit($this->wallet(), 1, 'test', null, 'topup');
        (new CollectPendingInfluencerFeesJob)->handle($this->svc);
        $this->assertSame('paid', $inv->fresh()->platform_fee_status);
        $this->assertSame('wallet', $inv->fresh()->platform_fee_paid_via);
        $this->assertSame(0, $this->wallet()->fresh()->balance);

        // Re-running (or a stale in-memory copy still saying "pending") must not debit again.
        app(WalletService::class)->credit($this->wallet(), 3000, 'test', null, 'topup');
        (new CollectPendingInfluencerFeesJob)->handle($this->svc);
        $stale = $inv->fresh();
        $stale->platform_fee_status = 'pending';
        $this->assertFalse($this->svc->collectInfluencerFee($stale));
        $this->assertSame(3000, $this->wallet()->fresh()->balance);
    }

    public function test_a_fee_already_on_a_statement_line_is_never_taken_from_the_wallet(): void
    {
        $inv = $this->accepted(); // empty wallet -> pending
        $payout = Payout::create([
            'payout_number' => 'PO-TEST-1', 'vendor_id' => $this->s->vendor->id, 'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(), 'gross_sales' => 0, 'commission' => 0, 'refunds_deducted' => 0,
            'chargebacks_deducted' => 0, 'net_amount' => 0, 'currency' => 'AED', 'status' => 'pending',
            'bank_account_id' => VendorBankAccount::create([
                'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'T', 'bank_name' => 'B', 'iban' => 'AE070331234567890123456',
                'account_number_encrypted' => 'enc', 'currency' => 'AED', 'is_primary' => true, 'verification_status' => 'verified',
            ])->id,
            'payout_method' => 'bank_transfer',
        ]);
        PayoutItem::create([
            'payout_id' => $payout->id, 'item_type' => 'influencer_fee', 'invitation_id' => $inv->id,
            'gross' => 0, 'commission' => 0, 'vat_amount' => 50, 'net' => -1050,
        ]);
        app(WalletService::class)->credit($this->wallet(), 5000, 'test', null, 'topup');

        $this->assertFalse($this->svc->collectInfluencerFee($inv));
        $this->assertSame(5000, $this->wallet()->fresh()->balance);
        $this->assertSame('pending', $inv->fresh()->platform_fee_status);
    }

    public function test_vendor_always_pays_even_when_the_marketer_payer_setting_is_set(): void
    {
        F::setting('marketer_influencer_fee_payer', 'marketer');
        $inv = $this->accepted(); // empty vendor wallet, empty marketer wallet: acceptance is not blocked

        $this->assertSame('accepted', $inv->status);
        $this->assertSame('vendor', $inv->platform_fee_payer);
        $this->assertSame('pending', $inv->platform_fee_status);
    }

    public function test_campaign_without_a_vendor_keeps_the_marketer_paid_branch_and_blocks_acceptance_when_short(): void
    {
        $campaign = F::campaign($this->s, [$inf = F::influencer($this->s)]);
        $this->svc->approveCampaign($campaign, Admin::factory()->create());
        $campaign->update(['vendor_id' => null]); // platform-owned campaign: no seller to bill
        $inv = $campaign->invitations()->sole();
        $wallets = app(WalletService::class);
        $mw = $wallets->getOrCreateWallet('marketer', $inf->id, 'AED');
        $wallets->credit($mw, 1049, 'test', null, 'topup');

        try {
            $this->svc->acceptInvitation($inv);
            $this->fail('acceptance must be blocked: balance < fee + VAT');
        } catch (InsufficientBalanceException) {
            $this->assertSame('pending', $inv->fresh()->status);
        }

        $wallets->credit($mw, 1, 'test', null, 'topup');
        $this->svc->acceptInvitation($inv->fresh());
        $this->assertSame('paid', $inv->fresh()->platform_fee_status);
        $this->assertSame('marketer', $inv->fresh()->platform_fee_payer);
        $this->assertSame(0, $mw->fresh()->balance);
        $this->assertSame(0, (int) $inv->fresh()->marketer_payout_amount, 'no vendor charge, no fee share');
    }

    public function test_affiliates_and_zero_fee_countries_have_no_fee_and_no_vat(): void
    {
        $campaign = F::campaign($this->s, [$aff = F::affiliate($this->s)]);
        $this->svc->approveCampaign($campaign, Admin::factory()->create());
        $inv = $campaign->invitations()->sole();
        $this->svc->acceptInvitation($inv);
        $inv->refresh();
        $this->assertSame('not_applicable', $inv->platform_fee_status);
        $this->assertSame(0, (int) $inv->platform_fee_amount);
        $this->assertSame(0, (int) $inv->platform_fee_vat_amount);
        $this->assertFalse($this->svc->collectInfluencerFee($inv));

        $this->setFee(0);
        $inv2 = $this->accepted();
        $this->assertSame('waived', $inv2->platform_fee_status);
        $this->assertSame(0, (int) $inv2->platform_fee_vat_amount);
    }

    public function test_quote_returns_fee_vat_and_total_per_influencer_and_overall(): void
    {
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);
        $aff = F::affiliate($this->s);

        $quote = app(MarketerCampaignQuoteService::class)->quote($this->s->vendorListingFbn, [$a->id, $b->id, $aff->id]);

        $this->assertEquals(2000, $quote['total_fee']);
        $this->assertEquals(5.0, $quote['vat_rate']);
        $this->assertEquals(50, $quote['fee_vat_per_influencer']);
        $this->assertEquals(100, $quote['total_fee_vat']);
        $this->assertEquals(2100, $quote['total_fee_with_vat']);
        $rows = collect($quote['rows'])->keyBy('id');
        $this->assertEquals(1050, $rows[$a->id]['fee_total']);
        $this->assertEquals(0, $rows[$aff->id]['fee_total'], 'affiliate = free, no VAT');
    }

    public function test_a_small_fee_keeps_its_fractional_vat_instead_of_rounding_to_zero(): void
    {
        $this->setFee(2); // 5% of 2 = 0.10 (was 0 when VAT was whole units)
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);

        $quote = app(MarketerCampaignQuoteService::class)->quote($this->s->vendorListingFbn, [$a->id, $b->id]);

        $this->assertEqualsWithDelta(0.10, $quote['fee_vat_per_influencer'], 0.00001);
        $this->assertEqualsWithDelta(4.0, $quote['total_fee'], 0.00001);
        $this->assertEqualsWithDelta(0.20, $quote['total_fee_vat'], 0.00001);
        $this->assertEqualsWithDelta(4.20, $quote['total_fee_with_vat'], 0.00001);
        $this->assertEqualsWithDelta(2.10, collect($quote['rows'])->firstWhere('id', $a->id)['fee_total'], 0.00001);
    }

    public function test_a_decimal_fee_is_stored_and_debited_exactly_with_its_vat(): void
    {
        $this->setFee(2.5); // VAT 5% = 0.125 -> 0.13 (half up), total 2.63
        $inv = $this->accepted(topUp: 10);

        $this->assertSame('paid', $inv->platform_fee_status);
        $this->assertEqualsWithDelta(2.5, (float) $inv->platform_fee_amount, 0.00001);
        $this->assertEqualsWithDelta(0.13, (float) $inv->platform_fee_vat_amount, 0.00001);
        $this->assertEqualsWithDelta(10 - 2.63, (float) $this->wallet()->fresh()->balance, 0.00001, 'fee + VAT debited with its decimals');
    }

    public function test_decimal_wallet_credit_and_debit_keep_their_cents(): void
    {
        $wallets = app(WalletService::class);
        $wallet = $this->wallet();
        $wallets->credit($wallet, 10.55, 'test', null, 'topup');
        $wallets->debit($wallet->fresh(), 0.30, 'test', null, 'fee');

        $this->assertEqualsWithDelta(10.25, (float) $wallet->fresh()->balance, 0.00001);
        $this->expectException(InsufficientBalanceException::class);
        $wallets->debit($wallet->fresh(), 10.26, 'test', null, 'too much');
    }
}
