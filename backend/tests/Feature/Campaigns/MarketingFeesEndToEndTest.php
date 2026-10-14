<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Enums\MarketingFeeType;
use App\Enums\VendorGlobalStatus;
use App\Jobs\AffiliateFeeSettlementJob;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignFeeAccrual;
use App\Models\MarketerCampaignFeeSettlement;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\Order;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AffiliateFeeSettlementService;
use App\Services\Customer\CartService;
use App\Services\Marketer\MarketerListingAvailabilityService;
use App\Services\MarketerCampaignService;
use App\Services\MarketingFeeReportService;
use App\Services\OrderStateMachine;
use App\Services\Vendor\FinanceService;
use App\Services\WalletService;
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
 * P9: the two complete marketing-fee journeys, driven through the real admin / vendor / marketer HTTP endpoints,
 * with the plan section 6 numbers asserted in every panel.
 *
 *  - INFLUENCER (UAE, 2 influencers, 20 % fee share): quote 22.69 -> store snapshot -> admin approve -> both accept
 *    (vendor wallet -22.69 = 11.35 + 11.34, marketing 1.90 + 1.89) -> marketers credited 1.80 each -> reports.
 *  - AFFILIATE (category default 10 %): order 1 qty 2 (commission 20.00, fees 3.90), order 2 qty 1 (+1.00 = 4.90),
 *    history in the three campaign pages, one 4.90 debit on finish / budget exhaustion, report totals.
 */
class MarketingFeesEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private Admin $admin;

    private VendorAdmin $vendorAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        $this->withoutVite();
        app()->setLocale('en');
        Cache::flush();

        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->s->vendor->update(['global_status' => VendorGlobalStatus::Active]);
        VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => 'AED',
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        F::setting('marketer_influencer_fee_payer', 'vendor');

        $adminPerms = [
            'vendors.assigned_only', 'payouts.approve', 'analytics.view', 'marketers.view',
            'marketer_campaigns.view', 'marketer_campaigns.approve',
            'marketer_commission_settings.view', 'marketer_commission_settings.edit', 'marketer_fee_settings.edit',
            'categories.view', 'categories.edit',
        ];
        foreach ($adminPerms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(array_slice($adminPerms, 1));

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit', 'marketer_campaigns.delete', 'finance.view', 'finance.payouts.view'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function wallets(): WalletService
    {
        return app(WalletService::class);
    }

    private function vendorWallet(): Wallet
    {
        return $this->wallets()->getOrCreateWallet('vendor', $this->s->vendor->id, 'AED');
    }

    private function vendorBalance(): float
    {
        return (float) $this->vendorWallet()->fresh()->balance;
    }

    private function topUp(float $amount): void
    {
        $this->wallets()->credit($this->vendorWallet(), $amount, 'test', null, 'topup');
    }

    private function marketerBalance(Marketer $m): float
    {
        return (float) $this->wallets()->getOrCreateWallet('marketer', $m->id, 'AED')->fresh()->balance;
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function asVendor()
    {
        return $this->actingAs($this->vendorAdmin, 'vendor');
    }

    /** Admin sets the platform-wide numbers through the real endpoints (marketing fees page + category Marketers tab). */
    private function adminConfiguresFeesAndShares(MarketingFeeType $type, float $sharePct, string $affiliateMode, float $affiliateRate): void
    {
        $country = $this->s->country->id;

        $this->asAdmin()->post(route('admin.marketer-settings.update-fee'), [
            'country_id' => $country, 'fee_per_influencer' => 9, 'currency' => 'AED',
        ])->assertSessionHasNoErrors();

        $this->asAdmin()->post(route('admin.marketing-fees.update-fixed'), [
            'country_id' => $country, 'fixed_amount' => 1.9, 'fixed_percentage' => 0,
        ])->assertSessionHasNoErrors();

        $this->asAdmin()->post(route('admin.marketing-fees.update-variable'), [
            'country_id' => $country, 'category_id' => $this->s->category->id, 'fulfillment_type' => $type->value,
            'fixed_amount' => 0, 'percentage' => 10,
        ])->assertSessionHasNoErrors();

        $this->asAdmin()->post(route('admin.categories.marketer-commission.update', $this->s->category->id), [
            'country_id' => $country, 'currency' => 'AED', 'influencer_fee_share_pct' => $sharePct,
            'affiliate_commission_mode' => $affiliateMode, 'affiliate_commission_rate' => $affiliateRate, 'affiliate_commission_flat' => 0,
        ])->assertSessionHasNoErrors();
    }

    /** @return array<int, float> */
    private function vendorDebits(string $sourceType): array
    {
        return WalletTransaction::where('wallet_id', $this->vendorWallet()->id)
            ->where('source_type', $sourceType)->orderBy('created_at')->orderBy('id')
            ->get()->map(fn ($t) => (float) $t->amount)->all();
    }

    // ── INFLUENCER ──────────────────────────────────────────────────────────

    public function test_influencer_flow_from_vendor_quote_to_every_report(): void
    {
        // 0. Admin configuration (fee 9, fixed 1.9 + 0 %, FBN variable 0 + 10 %, fee share 20 %).
        $this->adminConfiguresFeesAndShares(MarketingFeeType::Fbn, 20, 'percentage', 10);
        $inf = [F::influencer($this->s), F::influencer($this->s)];
        $ids = array_map(fn ($m) => $m->id, $inf);
        $listing = $this->s->vendorListingFbn;

        // 1. Vendor opens the create form and gets the server-side quote (no client math): 22.69, wallet empty.
        $this->asVendor()->get(route('partner.marketer-campaigns.create', $listing->id))->assertOk();
        $quote = $this->asVendor()->getJson(route('partner.listings.marketer-fee', ['listing_id' => $listing->id, 'marketer_ids' => $ids]))
            ->assertOk()->json();
        $this->assertEqualsWithDelta(18.00, $quote['total_fee'], 0.001);
        $this->assertEqualsWithDelta(0.90, $quote['total_fee_vat'], 0.001);
        $this->assertEqualsWithDelta(18.90, $quote['campaign_subtotal'], 0.001);
        $this->assertEqualsWithDelta(1.90, $quote['marketing']['fixed_fee'], 0.001);
        $this->assertEqualsWithDelta(1.89, $quote['marketing']['variable_fee'], 0.001);
        $this->assertEqualsWithDelta(3.79, $quote['marketing']['total'], 0.001);
        $this->assertEqualsWithDelta(22.69, $quote['campaign_grand_total'], 0.001);
        $this->assertFalse($quote['wallet']['sufficient']);
        $this->assertCount(2, $quote['rows']);
        foreach ($quote['rows'] as $row) {
            $this->assertEqualsWithDelta(1.80, $row['marketer_payout'], 0.001);
            $this->assertEqualsWithDelta(7.20, $row['platform_keeps'], 0.001);
        }

        // 2. Vendor tops up and submits: the snapshot is the 22.69 (client numbers ignored). Nothing is charged yet.
        $this->topUp(100);
        $this->asVendor()->post(route('partner.marketer-campaigns.store'), [
            'vendor_listing_id' => $listing->id, 'marketer_ids' => $ids, 'campaign_grand_total' => 1, 'marketing_fee_total' => 0,
        ])->assertSessionHasNoErrors();
        $campaign = MarketerCampaign::where('vendor_listing_id', $listing->id)->sole();
        $this->assertSame(MarketingFeeType::Fbn, $campaign->fulfillment_type);
        $this->assertEqualsWithDelta(18.90, (float) $campaign->campaign_subtotal, 0.0001);
        $this->assertEqualsWithDelta(1.90, (float) $campaign->marketing_fee_fixed, 0.0001);
        $this->assertEqualsWithDelta(1.89, (float) $campaign->marketing_fee_variable, 0.0001);
        $this->assertEqualsWithDelta(3.79, (float) $campaign->marketing_fee_total, 0.0001);
        $this->assertEqualsWithDelta(22.69, (float) $campaign->campaign_grand_total, 0.0001);
        $this->assertEqualsWithDelta(100.00, $this->vendorBalance(), 0.0001);

        // 3. Admin approves: invitations are created, still nothing charged.
        $this->asAdmin()->get(route('admin.marketer-campaigns.show', $campaign->id))->assertOk()
            ->assertSee('data-testid="campaign-fee-snapshot"', false)->assertSee('22.69');
        $this->asAdmin()->post(route('admin.marketer-campaigns.approve', $campaign->id))->assertSessionHasNoErrors();
        $campaign->refresh();
        $this->assertSame('active', $campaign->status);
        $this->assertCount(2, $campaign->invitations);
        $this->assertEqualsWithDelta(100.00, $this->vendorBalance(), 0.0001);

        // 4. Both influencers accept from the marketer panel: vendor -22.69, marketers +1.80 each.
        foreach ($inf as $i => $m) {
            $invitation = $campaign->invitations()->where('marketer_id', $m->id)->sole();
            $this->actingAs(F::marketerLogin($m), 'marketer')
                ->post(route('marketer.invitations.accept', $invitation->id))->assertSessionHasNoErrors();
        }
        $this->assertEqualsWithDelta(100 - 22.69, $this->vendorBalance(), 0.0001);
        $this->assertEqualsWithDelta(22.69, array_sum($this->vendorDebits('marketer_campaign_invitation_platform_fee')), 0.0001);
        $invitations = $campaign->invitations()->orderByDesc('platform_fee_marketing_amount')->get();
        $this->assertEqualsWithDelta(1.90, (float) $invitations[0]->platform_fee_marketing_amount, 0.0001);
        $this->assertEqualsWithDelta(1.89, (float) $invitations[1]->platform_fee_marketing_amount, 0.0001);
        foreach ($invitations as $invitation) {
            $this->assertSame('paid', $invitation->platform_fee_status);
            $this->assertSame('vendor', $invitation->platform_fee_payer);
            $this->assertEqualsWithDelta(9.00, (float) $invitation->platform_fee_amount, 0.0001);
            $this->assertEqualsWithDelta(0.45, (float) $invitation->platform_fee_vat_amount, 0.0001);
            $this->assertEqualsWithDelta(20.0, (float) $invitation->marketer_fee_share_pct, 0.0001);
            $this->assertEqualsWithDelta(1.80, (float) $invitation->marketer_payout_amount, 0.0001);
            $this->assertNotNull($invitation->marketer_credited_at);
        }
        $this->assertEqualsWithDelta(22.69, $invitations->sum(fn ($i) => $i->platformFeeTotal()), 0.0001);
        foreach ($inf as $m) {
            $this->assertEqualsWithDelta(1.80, $this->marketerBalance($m), 0.0001);
        }

        // 5. Exactly one fee-share credit per influencer.
        $this->assertCount(2, WalletTransaction::where('source_type', 'marketer_campaign_fee_share')->get());

        $this->assertInfluencerReports($campaign, $inf, $invitations->first());
    }

    /** @param array<int, Marketer> $inf */
    private function assertInfluencerReports(MarketerCampaign $campaign, array $inf, MarketerCampaignInvitation $anyInvitation): void
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        // Admin finance report (page + JSON + CSV).
        $this->asAdmin()->get(route('admin.reports.financial.index'))->assertOk()->assertSee('marketing-fees-section', false);
        $rows = collect($this->asAdmin()->getJson(route('admin.reports.financial.data', ['from' => $from, 'to' => $to]))
            ->assertOk()->json('marketing.rows'));
        $fbn = $rows->first(fn ($r) => $r['type'] === 'fbn' && $r['currency'] === 'AED');
        $this->assertEqualsWithDelta(18.00, $fbn['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(0.90, $fbn['influencer_vat'], 0.001);
        $this->assertEqualsWithDelta(3.79, $fbn['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(3.60, $fbn['payouts_net'], 0.001);
        $this->assertEqualsWithDelta(18.19, $fbn['platform_margin'], 0.001);
        $this->assertEqualsWithDelta(0.90, $fbn['vat_liability'], 0.001);
        $csv = $this->asAdmin()->get(route('admin.reports.financial.export', ['from' => $from, 'to' => $to, 'section' => 'marketing', 'format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('18.19', $csv);

        // Marketing Fees "Report" tab.
        $tab = $this->asAdmin()->get(route('admin.marketing-fees.index', ['tab' => 'report']))->assertOk()
            ->assertSee('data-marketing-fee-report', false)->assertSee('22.69');
        $row = collect($tab->viewData('report')['campaigns'])->first(fn ($r) => $r['type'] === 'fbn' && $r['currency'] === 'AED');
        $this->assertSame(2, (int) $row['influencers']);
        $this->assertEqualsWithDelta(18.90, $row['subtotal'], 0.001);
        $this->assertEqualsWithDelta(3.79, $row['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(22.69, $row['grand_total'], 0.001);
        $this->assertEqualsWithDelta(22.69, $row['collected'], 0.001);
        $this->assertEqualsWithDelta(0.0, $row['pending'], 0.001);

        // Admin campaign page: per-invitation payout, marketing share and the snapshot.
        $this->asAdmin()->get(route('admin.marketer-campaigns.show', $campaign->id))->assertOk()
            ->assertSee('data-testid="admin-invitation-marketing"', false)
            ->assertSee('data-testid="admin-invitation-payout"', false)
            ->assertSee('22.69');

        // Admin marketer profile.
        $profile = $this->asAdmin()->get(route('admin.marketers.show', $inf[0]->id))->assertOk()->assertSee('data-campaign-fee-earnings', false);
        $earn = $profile->viewData('earnings')['fee_share'];
        $this->assertEqualsWithDelta(1.80, $earn['rows'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(20.0, $earn['rows'][0]['share_pct'], 0.001);
        $this->assertSame('credited', $earn['rows'][0]['state']);
        $this->assertEqualsWithDelta(1.80, $earn['totals']['AED']['net'], 0.001);

        // Marketer panel: reports, wallet and the API.
        $login = F::marketerLogin($inf[1]);
        $reports = $this->actingAs($login, 'marketer')->get(route('marketer.reports.index'))->assertOk()->assertSee('data-fee-share-report', false);
        $this->assertEqualsWithDelta(1.80, $reports->viewData('feeShare')['totals']['AED']['credited'], 0.001);
        $this->actingAs($login, 'marketer')->get(route('marketer.finance.wallet'))->assertOk()
            ->assertSee('data-tx-source="marketer_campaign_fee_share"', false);
        $api = MarketerAdmin::create(['marketer_id' => $inf[1]->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test', 'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true]);
        $apiReports = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/finance/reports')->assertOk();
        $this->assertEqualsWithDelta(1.80, $apiReports->json('data.fee_share.totals.AED.credited'), 0.001);
        $wallet = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/finance/wallet')->assertOk();
        $this->assertEqualsWithDelta(1.80, $wallet->json('data.fee_share.credited'), 0.001);

        // Vendor pages: campaign page, campaign list, finance transactions + summary.
        $this->asVendor()->get(route('partner.marketer-campaigns.show', $campaign->id))->assertOk()
            ->assertSee('data-testid="campaign-fee-snapshot"', false)->assertSee('22.69')
            ->assertSee('data-testid="invitation-fee-marketing"', false);
        $this->asVendor()->get(route('partner.marketer-campaigns.index'))->assertOk()->assertSee('data-marketing-totals', false);
        $tx = $this->asVendor()->get(route('partner.finance.transactions', ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]))
            ->assertOk()->assertSee('data-marketing-totals', false);
        $aed = $tx->viewData('marketingTotals')['AED'];
        $this->assertEqualsWithDelta(18.90, $aed['influencer_fees'], 0.001, 'fee + VAT charged to the vendor');
        $this->assertEqualsWithDelta(3.79, $aed['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(0.0, $aed['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(0.0, $aed['refunds'], 0.001);
        $summary = app(FinanceService::class)->getSummary($this->s->vendor->fresh());
        $this->assertEqualsWithDelta(3.79, $summary['marketing']['AED']['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(
            22.69,
            $aed['influencer_fees'] + $aed['marketing_fees'],
            0.001,
            'the vendor pages add up to the 22.69 wallet debit'
        );
    }

    // ── AFFILIATE ───────────────────────────────────────────────────────────

    /** Affiliate scenario: the campaign has no typed per-unit amount, so the 10 % category default applies. */
    private function affiliateSetUp(): MarketerCampaign
    {
        $this->adminConfiguresFeesAndShares(MarketingFeeType::Fbp, 20, 'percentage', 10);

        $job = MarketerJob::firstOrCreate(['key' => 'affiliate'], ['name_ar' => 'affiliate', 'name_en' => 'affiliate', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $this->s->marketer->id, 'marketer_job_id' => $job->id]);
        $this->s->marketer->update(['onboarding_completed_at' => now()]);

        // order_items.line_total includes customer VAT and the commission base is that line_total (existing behaviour,
        // listed in the final summary): a VAT-free country keeps the plan example at exactly 200 / 100.
        $this->s->country->update(['vat_rate' => 0]);

        $campaign = $this->s->marketerCampaign;
        $campaign->update(['marketer_commission_amount' => 0, 'max_commission_budget' => 100000]);
        // Marketer price must stay inside the source listing's price band, so both move to the plan's 100 AED.
        $this->s->vendorListingFbp->update(['price' => 100]);
        $this->s->marketerListing->update(['price' => 100]);
        app(MarketerListingAvailabilityService::class)->syncListing($this->s->marketerListing->fresh());
        $this->assertSame('active', $this->s->marketerListing->fresh()->status->value);

        return $campaign->fresh();
    }

    /** Places an order of $qty units of the affiliate's listing, delivers it and (optionally) runs the approval job. */
    private function affiliateOrder(int $qty, bool $approve = true): MarketerCampaignConversion
    {
        $s = $this->s;
        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id, 'quantity' => $qty,
            'unit_price' => 100, 'added_at' => now(),
        ]);
        $res = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame(201, $res->status(), "qty {$qty}: ".$res->getContent());
        $order = Order::where('order_number', $res->json('data.order.order_number') ?? $res->json('data.order_number'))->firstOrFail();

        $item = $order->items()->get()->firstWhere('marketer_listing_id', $s->marketerListing->id);
        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();

        $machine = app(OrderStateMachine::class);
        foreach ($order->subOrders()->get() as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
        }
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        if ($approve) {
            app()->call([new ApproveMarketerConversionsJob, 'handle']);
        }

        return $conversion->fresh();
    }

    /** Both orders of plan section 6, then the history pages of the three panels. */
    private function twoApprovedOrders(MarketerCampaign $campaign): void
    {
        $first = $this->affiliateOrder(2);
        $this->assertEqualsWithDelta(20.00, (float) $first->commission_amount, 0.0001, '10 % of the 200 line');
        $this->assertSame('approved', $first->status);

        $accrual = MarketerCampaignFeeAccrual::where('campaign_id', $campaign->id)->sole();
        $this->assertSame(2, (int) $accrual->quantity);
        $this->assertEqualsWithDelta(200.00, (float) $accrual->line_total, 0.0001);
        $this->assertEqualsWithDelta(1.90, (float) $accrual->marketing_fixed_delta, 0.0001);
        $this->assertEqualsWithDelta(2.00, (float) $accrual->marketing_variable_delta, 0.0001);
        $this->assertEqualsWithDelta(3.90, (float) $accrual->running_marketing_total, 0.0001);

        $second = $this->affiliateOrder(1);
        $this->assertEqualsWithDelta(10.00, (float) $second->commission_amount, 0.0001);
        $accruals = MarketerCampaignFeeAccrual::where('campaign_id', $campaign->id)->orderBy('created_at')->orderBy('id')->get();
        $this->assertCount(2, $accruals);
        $this->assertEqualsWithDelta(1.00, (float) $accruals[1]->marketing_total_delta, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $accruals[1]->marketing_fixed_delta, 0.0001, 'the flat fee is accrued once');
        $this->assertEqualsWithDelta(4.90, (float) $accruals[1]->running_marketing_total, 0.0001);
        $this->assertEqualsWithDelta(4.90, (float) $campaign->fresh()->affiliate_fee_accrued, 0.0001);

        // History visible in vendor, admin and marketer campaign pages (2 rows, running total 4.90).
        $vendor = $this->asVendor()->get(route('partner.marketer-campaigns.show', $campaign->id))->assertOk()
            ->assertSee('data-testid="affiliate-fee-history"', false)->assertSee('4.90');
        $this->assertSame(2, substr_count($vendor->getContent(), 'data-testid="affiliate-fee-row"'));
        $admin = $this->asAdmin()->get(route('admin.marketer-campaigns.show', $campaign->id))->assertOk()
            ->assertSee('data-testid="affiliate-fee-history"', false)->assertSee('affiliate-settle-now', false)->assertSee('4.90');
        $this->assertSame(2, substr_count($admin->getContent(), 'data-testid="affiliate-fee-row"'));
        $marketer = $this->actingAs(F::marketerLogin($this->s->marketer), 'marketer')
            ->get(route('marketer.campaigns.show', $this->s->marketerCampaignInvitation->id))->assertOk()
            ->assertSee('data-testid="affiliate-fee-history"', false);
        $this->assertSame(2, substr_count($marketer->getContent(), 'data-testid="affiliate-fee-row"'));
    }

    private function assertAffiliateReports(MarketerCampaign $campaign, float $settled): void
    {
        $from = now()->startOfMonth();
        $to = now();

        // Marketing Fees report tab + admin finance report.
        $report = $this->asAdmin()->get(route('admin.marketing-fees.index', ['tab' => 'report']))->assertOk()->viewData('report');
        $fbp = collect($report['affiliate'])->first(fn ($r) => $r['type'] === 'fbp' && $r['currency'] === 'AED');
        $this->assertSame(2, (int) $fbp['orders']);
        $this->assertEqualsWithDelta(30.00, $fbp['commission'], 0.001);
        $this->assertEqualsWithDelta(4.90, $fbp['accrued'], 0.001);
        $this->assertEqualsWithDelta($settled, $fbp['settled'], 0.001);

        $line = collect($this->asAdmin()->getJson(route('admin.reports.financial.data', ['from' => $from->toDateString(), 'to' => $to->toDateString()]))
            ->assertOk()->json('marketing.rows'))->first(fn ($r) => $r['type'] === 'fbp' && $r['currency'] === 'AED');
        $this->assertEqualsWithDelta($settled, $line['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(30.00, $line['affiliate_commissions'], 0.001);

        // Admin marketer profile + marketer panel (commission only, never the vendor's fees) + API.
        $aff = $this->asAdmin()->get(route('admin.marketers.show', $this->s->marketer->id))->assertOk()->viewData('earnings')['affiliate'];
        $this->assertSame(2, $aff['totals']['AED']['orders']);
        $this->assertEqualsWithDelta(30.00, $aff['totals']['AED']['commission'], 0.001);
        $this->assertEqualsWithDelta(4.90, $aff['totals']['AED']['marketing_fees'], 0.001);

        $login = F::marketerLogin($this->s->marketer);
        $reports = $this->actingAs($login, 'marketer')->get(route('marketer.reports.index'))->assertOk()->assertSee('data-affiliate-report', false);
        $this->assertEqualsWithDelta(30.00, $reports->viewData('affiliate')['totals']['AED']['commission'], 0.001);
        $api = MarketerAdmin::create(['marketer_id' => $this->s->marketer->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test', 'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true]);
        $res = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/finance/reports')->assertOk();
        $this->assertEqualsWithDelta(30.00, $res->json('data.affiliate.totals.AED.commission'), 0.001);
        $this->assertArrayNotHasKey('marketing_fees', $res->json('data.affiliate.totals.AED'));

        // Vendor pages show the accrued fees as affiliate marketing fees.
        $totals = app(MarketingFeeReportService::class)->vendorTotals($this->s->vendor->id);
        $this->assertEqualsWithDelta($settled, $totals['AED']['affiliate_marketing_fees'], 0.001);
        $this->asVendor()->get(route('partner.marketer-campaigns.index'))->assertOk()->assertSee('data-marketing-totals', false);
    }

    public function test_affiliate_flow_accrues_per_order_and_settles_once_when_the_campaign_finishes(): void
    {
        $campaign = $this->affiliateSetUp();
        $this->topUp(50);

        $this->twoApprovedOrders($campaign);
        $this->assertSame([], $this->vendorDebits('marketer_campaign_affiliate_marketing_fee'), 'nothing is debited per order');
        $this->assertEqualsWithDelta(50.00, $this->vendorBalance(), 0.0001);

        // The campaign finishes (twice, plus the hourly job): one 4.90 debit.
        app(MarketerCampaignService::class)->markCampaignDone($campaign->fresh());
        app(AffiliateFeeSettlementService::class)->settleIfClosed($campaign->fresh());
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));

        $this->assertEqualsWithDelta(4.90, array_sum($this->vendorDebits('marketer_campaign_affiliate_marketing_fee')), 0.0001);
        $this->assertCount(1, $this->vendorDebits('marketer_campaign_affiliate_marketing_fee'));
        $this->assertEqualsWithDelta(50 - 4.90, $this->vendorBalance(), 0.0001);
        $settlement = MarketerCampaignFeeSettlement::where('campaign_id', $campaign->id)->sole();
        $this->assertSame('paid', $settlement->status);
        $this->assertEqualsWithDelta(4.90, (float) $settlement->amount, 0.0001);
        $this->assertSame(0, MarketerCampaignFeeAccrual::where('campaign_id', $campaign->id)->where('status', 'accrued')->count());

        $this->asVendor()->get(route('partner.marketer-campaigns.show', $campaign->id))->assertOk()
            ->assertSee('data-testid="affiliate-settled-amount"', false);
        $this->assertAffiliateReports($campaign, 4.90);
    }

    public function test_affiliate_budget_exhaustion_debits_the_accrued_fees_once(): void
    {
        $campaign = $this->affiliateSetUp();
        $this->topUp(50);
        $this->twoApprovedOrders($campaign);
        $this->assertSame([], $this->vendorDebits('marketer_campaign_affiliate_marketing_fee'));

        // 20 + 10 spent; the third sale (+10) crosses the 35 budget: the campaign auto-pauses and settles 4.90.
        $campaign->update(['max_commission_budget' => 35]);
        $third = $this->affiliateOrder(1, approve: false);
        $this->assertSame('paused', $campaign->fresh()->status);

        $this->assertSame([4.90], array_map(fn ($a) => round($a, 2), $this->vendorDebits('marketer_campaign_affiliate_marketing_fee')));
        (new AffiliateFeeSettlementJob)->handle(app(AffiliateFeeSettlementService::class));
        app(AffiliateFeeSettlementService::class)->settleIfClosed($campaign->fresh(), 'budget_exhausted');
        $this->assertCount(1, $this->vendorDebits('marketer_campaign_affiliate_marketing_fee'), 'idempotent');
        $this->assertEqualsWithDelta(50 - 4.90, $this->vendorBalance(), 0.0001);
        $this->assertSame('paid', MarketerCampaignFeeSettlement::where('campaign_id', $campaign->id)->sole()->status);

        // The late approval of the sale that exhausted the budget accrues its own 1.00 and is settled on arrival,
        // so a sale is never charged twice and never left out.
        app()->call([new ApproveMarketerConversionsJob, 'handle']);
        $this->assertSame('approved', $third->fresh()->status);
        $this->assertEqualsWithDelta(4.90 + 1.00, array_sum($this->vendorDebits('marketer_campaign_affiliate_marketing_fee')), 0.0001);
        $this->assertCount(2, $this->vendorDebits('marketer_campaign_affiliate_marketing_fee'));
    }
}
