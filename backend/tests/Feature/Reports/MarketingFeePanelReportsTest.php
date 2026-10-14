<?php

namespace Tests\Feature\Reports;

use App\Enums\MarketingFeeType;
use App\Models\Admin;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaignInvitation;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Services\MarketingFeeReportService;
use App\Services\Vendor\FinanceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketingFeeReportCase;

/**
 * P8: the Marketing Fees "Report" tab, the admin marketer profile, the marketer panel (reports, wallet, API) and the
 * vendor pages, all on the plan section 6 fixtures (see MarketingFeeReportCase).
 */
class MarketingFeePanelReportsTest extends MarketingFeeReportCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        Cache::flush();
    }

    private function admin(array $permissions): Admin
    {
        foreach (array_merge(['vendors.assigned_only'], $permissions) as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function vendorAdmin(): VendorAdmin
    {
        $va = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'finance.view', 'finance.payouts.view'] as $name) {
            $va->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }

        return $va;
    }

    // ── Marketing Fees "Report" tab ─────────────────────────────────────────

    public function test_report_tab_lists_per_type_numbers_from_the_snapshots(): void
    {
        $res = $this->actingAs($this->admin(['marketer_commission_settings.view']), 'admin')
            ->get(route('admin.marketing-fees.index', ['tab' => 'report']));

        $res->assertOk()->assertSee('data-marketing-fee-report', false)->assertDontSee('data-report-stub', false);

        $report = $res->viewData('report');
        $fbn = $this->row($report['campaigns'], 'fbn', 'AED', $this->s->country->id);
        $this->assertSame(1, (int) $fbn['campaigns']);
        $this->assertSame(2, (int) $fbn['influencers']);
        $this->assertEqualsWithDelta(18.90, $fbn['subtotal'], 0.001);
        $this->assertEqualsWithDelta(1.90, $fbn['fixed_fee'], 0.001);
        $this->assertEqualsWithDelta(1.89, $fbn['variable_fee'], 0.001);
        $this->assertEqualsWithDelta(3.79, $fbn['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(22.69, $fbn['grand_total'], 0.001);
        $this->assertEqualsWithDelta(22.69, $fbn['collected'], 0.001);
        $this->assertEqualsWithDelta(0.0, $fbn['pending'], 0.001);

        $res->assertSee('22.69')->assertSee(MarketingFeeType::Fbn->label());
    }

    public function test_report_tab_affiliate_accruals_accrued_vs_settled(): void
    {
        $report = $this->actingAs($this->admin(['marketer_commission_settings.view']), 'admin')
            ->get(route('admin.marketing-fees.index', ['tab' => 'report']))->viewData('report');

        $fbp = $this->row($report['affiliate'], 'fbp', 'AED', $this->s->country->id);
        $this->assertSame(2, (int) $fbp['orders']);
        $this->assertEqualsWithDelta(30.00, $fbp['commission'], 0.001);
        $this->assertEqualsWithDelta(4.90, $fbp['accrued'], 0.001);
        $this->assertEqualsWithDelta(0.0, $fbp['refunded'], 0.001);
        $this->assertEqualsWithDelta(4.90, $fbp['settled'], 0.001);
        $this->assertEqualsWithDelta(0.0, $fbp['pending'], 0.001);
    }

    public function test_report_tab_filters_by_currency_type_and_dates_and_never_blends_currencies(): void
    {
        $admin = $this->admin(['marketer_commission_settings.view']);

        $sar = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketing-fees.index', ['tab' => 'report', 'currency' => 'SAR']))->viewData('report');
        $this->assertSame(['SAR'], array_values(array_unique(array_column($sar['campaigns'], 'currency'))));
        $this->assertEqualsWithDelta(13.50, $this->row($sar['campaigns'], 'total', 'SAR')['grand_total'], 0.001);
        $this->assertSame([], $sar['affiliate']);

        $fbm = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketing-fees.index', ['tab' => 'report', 'type' => 'fbm']))->viewData('report');
        $this->assertSame(['fbm', 'total'], array_column($fbm['campaigns'], 'type'));
        $this->assertEqualsWithDelta(10.95, $fbm['campaigns'][0]['grand_total'], 0.001);

        $past = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketing-fees.index', ['tab' => 'report', 'from' => '2020-01-01', 'to' => '2020-01-31']))->viewData('report');
        $this->assertSame([], $past['campaigns']);
    }

    public function test_report_tab_csv_export(): void
    {
        $res = $this->actingAs($this->admin(['marketer_commission_settings.view']), 'admin')
            ->get(route('admin.marketing-fees.report.export', ['currency' => 'AED']));

        $res->assertOk();
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($res->streamedContent()))));
        $this->assertSame('Section', $lines[0][0]);

        $fbn = collect($lines)->first(fn ($l) => ($l[3] ?? '') === MarketingFeeType::Fbn->label() && ($l[0] ?? '') === __('marketing_fees.reports.section_influencer'));
        $this->assertSame('22.69', $fbn[10]);
        $this->assertSame('3.79', $fbn[9]);

        $aff = collect($lines)->first(fn ($l) => ($l[3] ?? '') === MarketingFeeType::Fbp->label() && ($l[0] ?? '') === __('marketing_fees.reports.section_affiliate'));
        $this->assertSame('4.90', $aff[13]);
        $this->assertSame('4.90', $aff[15]);
    }

    public function test_report_tab_requires_the_view_permission(): void
    {
        $this->actingAs($this->admin([]), 'admin')->get(route('admin.marketing-fees.index', ['tab' => 'report']))->assertForbidden();
        $this->actingAs($this->admin([]), 'admin')->get(route('admin.marketing-fees.report.export'))->assertForbidden();
    }

    // ── Admin marketer profile ──────────────────────────────────────────────

    public function test_admin_marketer_profile_shows_fee_share_earnings_and_wallet(): void
    {
        $inv = MarketerCampaignInvitation::where('campaign_id', $this->influencerCampaign->id)->where('marketer_id', $this->influencers[0]->id)->sole();

        $res = $this->actingAs($this->admin(['marketers.view']), 'admin')
            ->get(route('admin.marketers.show', $this->influencers[0]));

        $res->assertOk()->assertSee('data-campaign-fee-earnings', false);
        $earnings = $res->viewData('earnings');
        $this->assertCount(1, $earnings['fee_share']['rows']);
        $row = $earnings['fee_share']['rows'][0];
        $this->assertSame($inv->id, $row['invitation_id']);
        $this->assertEqualsWithDelta(9.00, $row['fee'], 0.001);
        $this->assertEqualsWithDelta(20.0, $row['share_pct'], 0.001);
        $this->assertEqualsWithDelta(1.80, $row['amount'], 0.001);
        $this->assertNotNull($row['credited_at']);
        $this->assertSame('credited', $row['state']);
        $this->assertEqualsWithDelta(1.80, $earnings['fee_share']['totals']['AED']['net'], 0.001);
        $this->assertEqualsWithDelta(1.80, (float) $res->viewData('marketerWallets')->first()->balance, 0.001);

        $res->assertSee($this->influencerCampaign->title ?: '#', false)
            ->assertSee('20%')
            ->assertSee(__('marketing_fees.reports.state_credited'))
            ->assertSee('data-wallet-balance', false);
    }

    public function test_admin_marketer_profile_shows_the_reversed_state_and_the_precedence_note(): void
    {
        $reversedInv = MarketerCampaignInvitation::where('platform_fee_status', 'refunded')->sole();

        $res = $this->actingAs($this->admin(['marketers.view']), 'admin')
            ->get(route('admin.marketers.show', $reversedInv->marketer_id));

        $row = $res->viewData('earnings')['fee_share']['rows'][0];
        $this->assertSame('reversed', $row['state']);
        $totals = $res->viewData('earnings')['fee_share']['totals']['AED'];
        $this->assertEqualsWithDelta(0.40, $totals['credited'], 0.001);
        $this->assertEqualsWithDelta(0.40, $totals['reversed'], 0.001);
        $this->assertEqualsWithDelta(0.0, $totals['net'], 0.001);
        $res->assertSee(__('marketing_fees.reports.state_reversed'));

        $res->assertSee('data-commission-precedence-note', false)
            ->assertSee(__('marketing_fees.reports.commission_precedence_note'));
    }

    public function test_admin_marketer_profile_shows_clawback_pending(): void
    {
        $inv = MarketerCampaignInvitation::where('campaign_id', $this->fbmCampaign->id)->where('platform_fee_status', 'paid')->sole();
        $inv->update(['marketer_clawback_amount' => 0.50, 'platform_fee_refunded_at' => now()]);

        $res = $this->actingAs($this->admin(['marketers.view']), 'admin')->get(route('admin.marketers.show', $inv->marketer_id));
        $row = $res->viewData('earnings')['fee_share']['rows'][0];
        $this->assertSame('clawback', $row['state']);
        $this->assertEqualsWithDelta(0.50, $row['clawback_amount'], 0.001);
        $this->assertEqualsWithDelta(0.0, $res->viewData('earnings')['fee_share']['totals']['AED']['net'], 0.001);
    }

    public function test_admin_marketer_profile_shows_affiliate_commissions(): void
    {
        $res = $this->actingAs($this->admin(['marketers.view']), 'admin')->get(route('admin.marketers.show', $this->s->marketer));

        $aff = $res->viewData('earnings')['affiliate'];
        $this->assertSame(2, $aff['totals']['AED']['orders']);
        $this->assertEqualsWithDelta(30.00, $aff['totals']['AED']['commission'], 0.001);
        $this->assertEqualsWithDelta(4.90, $aff['totals']['AED']['marketing_fees'], 0.001);
        $res->assertSee('data-affiliate-row', false);
    }

    // ── Marketer panel ──────────────────────────────────────────────────────

    public function test_marketer_reports_page_shows_fee_share_per_campaign_and_totals(): void
    {
        $login = F::marketerLogin($this->influencers[0]);

        $res = $this->actingAs($login, 'marketer')->get(route('marketer.reports.index'));

        $res->assertOk()->assertSee('data-fee-share-report', false);
        $this->assertEqualsWithDelta(1.80, $res->viewData('feeShare')['totals']['AED']['credited'], 0.001);
        $this->assertCount(1, $res->viewData('feeShare')['rows']);
        $res->assertSee(__('marketing_fees.reports.state_credited'))->assertDontSee('data-affiliate-report', false);
    }

    public function test_marketer_reports_page_shows_affiliate_campaign_totals_without_vendor_fees(): void
    {
        $login = F::marketerLogin($this->s->marketer);

        $res = $this->actingAs($login, 'marketer')->get(route('marketer.reports.index'));

        $res->assertOk()->assertSee('data-affiliate-report', false);
        $totals = $res->viewData('affiliate')['totals']['AED'];
        $this->assertSame(2, $totals['orders']);
        $this->assertEqualsWithDelta(30.00, $totals['commission'], 0.001);
        $res->assertDontSee('4.90', false); // marketing fees are vendor money
    }

    public function test_marketer_wallet_labels_fee_share_credits(): void
    {
        $login = F::marketerLogin($this->influencers[0]);

        $res = $this->actingAs($login, 'marketer')->get(route('marketer.finance.wallet'));

        $res->assertOk()
            ->assertSee('data-tx-source="marketer_campaign_fee_share"', false)
            ->assertSee(__('marketer.finance.tx_source.fee_share'))
            ->assertSee('data-wallet-fee-share', false);
        $this->assertEqualsWithDelta(1.80, (float) $res->viewData('wallet')->balance, 0.001);
    }

    public function test_marketer_wallet_labels_the_reversal(): void
    {
        $reversedInv = MarketerCampaignInvitation::where('platform_fee_status', 'refunded')->sole();

        $this->actingAs(F::marketerLogin($reversedInv->marketer), 'marketer')->get(route('marketer.finance.wallet'))
            ->assertOk()
            ->assertSee('data-tx-source="marketer_campaign_fee_share_reversal"', false)
            ->assertSee(__('marketer.finance.tx_source.fee_share_reversal'));
    }

    public function test_marketer_api_exposes_labels_and_report_figures(): void
    {
        $m = $this->influencers[0];
        $api = MarketerAdmin::create(['marketer_id' => $m->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test', 'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true]);

        $wallet = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/finance/wallet')->assertOk();
        $tx = collect($wallet->json('data.transactions'))->firstWhere('source_type', 'marketer_campaign_fee_share');
        $this->assertSame(__('marketer.finance.tx_source.fee_share'), $tx['source_label']);
        $this->assertEqualsWithDelta(1.80, $wallet->json('data.fee_share.credited'), 0.001);

        $reports = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/finance/reports')->assertOk();
        $this->assertEqualsWithDelta(1.80, $reports->json('data.fee_share.totals.AED.credited'), 0.001);
        $this->assertEqualsWithDelta(1.80, $reports->json('data.fee_share.rows.0.amount'), 0.001);
        $this->assertSame('credited', $reports->json('data.fee_share.rows.0.state'));
        $this->assertSame(__('marketer.finance.tx_source.fee_share_reversal'), $reports->json('data.transaction_labels.marketer_campaign_fee_share_reversal'));

        $stats = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/reports')->assertOk();
        $this->assertEqualsWithDelta(1.80, $stats->json('stats.fee_share.AED.credited'), 0.001);
    }

    public function test_marketer_api_affiliate_totals_hide_the_vendor_marketing_fees(): void
    {
        $api = MarketerAdmin::create(['marketer_id' => $this->s->marketer->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test', 'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true]);

        $res = $this->actingAs($api, 'marketer_api')->getJson('/api/marketer/finance/reports')->assertOk();
        $this->assertEqualsWithDelta(30.00, $res->json('data.affiliate.totals.AED.commission'), 0.001);
        $this->assertSame(2, $res->json('data.affiliate.totals.AED.orders'));
        $this->assertArrayNotHasKey('marketing_fees', $res->json('data.affiliate.totals.AED'));
        $this->assertArrayNotHasKey('marketing_fees', $res->json('data.affiliate.rows.0'));
    }

    // ── Vendor panel ────────────────────────────────────────────────────────

    public function test_vendor_totals_are_separate_per_kind_and_per_currency(): void
    {
        $totals = app(MarketingFeeReportService::class)->vendorTotals($this->s->vendor->id);

        $this->assertEqualsWithDelta(18.90 + 9.45, $totals['AED']['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(3.79 + 1.50, $totals['AED']['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $totals['AED']['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.70, $totals['AED']['refunds'], 0.001);
        $this->assertEqualsWithDelta(11.50, $totals['SAR']['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(2.00, $totals['SAR']['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(0.0, $totals['SAR']['affiliate_marketing_fees'], 0.001);
    }

    public function test_vendor_transactions_page_shows_the_four_totals(): void
    {
        $res = $this->actingAs($this->vendorAdmin(), 'vendor')
            ->get(route('partner.finance.transactions', ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]));

        $res->assertOk()->assertSee('data-marketing-totals', false);
        $aed = $res->viewData('marketingTotals')['AED'];
        $this->assertEqualsWithDelta(28.35, $aed['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(5.29, $aed['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $aed['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.70, $aed['refunds'], 0.001);
        $res->assertSee(__('marketing_fees.reports.vendor_marketing_fees'))
            ->assertSee(__('marketing_fees.reports.vendor_affiliate_fees'))
            ->assertSee(__('marketing_fees.reports.vendor_refunds'));

        $this->assertArrayHasKey('SAR', $res->viewData('marketingTotals'));
    }

    public function test_vendor_transactions_totals_follow_the_date_range(): void
    {
        $res = $this->actingAs($this->vendorAdmin(), 'vendor')
            ->get(route('partner.finance.transactions', ['date_from' => '2020-01-01', 'date_to' => '2020-01-31']));

        $res->assertOk()->assertDontSee('data-marketing-totals', false);
        $this->assertSame([], $res->viewData('marketingTotals'));
    }

    public function test_vendor_payout_statement_shows_separate_totals(): void
    {
        $bank = VendorBankAccount::where('vendor_id', $this->s->vendor->id)->firstOrFail();
        $payout = Payout::create([
            'payout_number' => 'PO-2026-'.random_int(10000, 99999), 'vendor_id' => $this->s->vendor->id,
            'period_start' => now()->subWeek()->toDateString(), 'period_end' => now()->toDateString(),
            'gross_sales' => 0, 'commission' => 0, 'refunds_deducted' => 0, 'chargebacks_deducted' => 0,
            'other_adjustments' => -27.79, 'net_amount' => -27.79, 'currency' => 'AED', 'status' => 'pending',
            'bank_account_id' => $bank->id, 'payout_method' => 'bank_transfer',
        ]);
        $invitations = MarketerCampaignInvitation::where('campaign_id', $this->influencerCampaign->id)->orderBy('platform_fee_marketing_amount', 'desc')->get();
        foreach ($invitations as $inv) {
            PayoutItem::create([
                'payout_id' => $payout->id, 'item_type' => 'influencer_fee', 'invitation_id' => $inv->id,
                'gross' => 0, 'commission' => 0, 'vat_amount' => $inv->platform_fee_vat_amount, 'marketing_fee_amount' => $inv->platform_fee_marketing_amount,
                'net' => -$inv->platformFeeTotal(), 'description' => 'Influencer fee',
            ]);
        }
        PayoutItem::create([
            'payout_id' => $payout->id, 'item_type' => 'affiliate_marketing_fee', 'gross' => 0, 'commission' => 0,
            'marketing_fee_amount' => 4.90, 'net' => -4.90, 'description' => 'Affiliate marketing fees',
        ]);

        $res = $this->actingAs($this->vendorAdmin(), 'vendor')->get(route('partner.payouts.show', $payout->payout_number));

        $res->assertOk()->assertSee('data-marketing-totals', false);
        $t = $res->viewData('statementMarketing')['AED'];
        $this->assertEqualsWithDelta(18.90, $t['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(3.79, $t['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $t['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.70, $t['refunds'], 0.001, 'refunds credited in the statement period');
    }

    public function test_vendor_finance_summary_has_the_separate_lines(): void
    {
        $summary = app(FinanceService::class)->getSummary($this->s->vendor->fresh());

        $this->assertEqualsWithDelta(28.35, $summary['marketing']['AED']['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(5.29, $summary['marketing']['AED']['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $summary['marketing']['AED']['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.70, $summary['marketing']['AED']['refunds'], 0.001);
        $this->assertArrayHasKey('SAR', $summary['marketing']);
    }

    public function test_vendor_campaign_list_shows_totals(): void
    {
        $res = $this->actingAs($this->vendorAdmin(), 'vendor')->get(route('partner.marketer-campaigns.index'));

        $res->assertOk()->assertSee('data-marketing-totals', false);
        $this->assertEqualsWithDelta(4.90, $res->viewData('marketingTotals')['AED']['affiliate_marketing_fees'], 0.001);
        $res->assertSee(__('marketing_fees.reports.vendor_campaign_totals_title'));
    }

    public function test_no_marketing_totals_for_a_vendor_without_campaign_charges(): void
    {
        $other = Vendor::factory()->create();
        $this->assertSame([], app(MarketingFeeReportService::class)->vendorTotals($other->id));
    }
}
