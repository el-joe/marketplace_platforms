<?php

namespace Tests\Feature\Reports;

use App\Enums\MarketingFeeType;
use App\Models\Admin;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Services\FinancialReportService;
use App\Services\MarketingFeeReportService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketingFeeReportCase;

/**
 * P8: admin finance report. Every figure is read from stored snapshots/ledgers (plan section 6): influencer
 * 18.00 fees, 0.90 VAT, 3.79 marketing, 3.60 payouts, platform margin 18.00 - 3.60 + 3.79 = 18.19; affiliate 4.90.
 */
class MarketingFeeFinancialReportTest extends MarketingFeeReportCase
{
    private function financeAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        foreach (['vendors.assigned_only', 'analytics.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }

        $admin->givePermissionTo('analytics.view');

        return $admin;
    }

    public function test_fbn_row_carries_the_section_six_influencer_numbers(): void
    {
        $row = $this->row($this->reportRows(), 'fbn', 'AED', $this->s->country->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(18.00, $row['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(0.90, $row['influencer_vat'], 0.001);
        $this->assertEqualsWithDelta(3.79, $row['influencer_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(0.0, $row['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(3.79, $row['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(3.60, $row['payouts_net'], 0.001);
        $this->assertEqualsWithDelta(0.0, $row['refunds'], 0.001);
        $this->assertEqualsWithDelta(18.19, $row['platform_margin'], 0.001);
        $this->assertEqualsWithDelta(0.90, $row['vat_liability'], 0.001);
        $this->assertEqualsWithDelta(22.69, $row['influencer_fees'] + $row['influencer_vat'] + $row['marketing_fees'], 0.001, 'vendor charged 22.69');
    }

    public function test_affiliate_numbers_land_on_the_fbp_row(): void
    {
        $row = $this->row($this->reportRows(), 'fbp', 'AED', $this->s->country->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(4.90, $row['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $row['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(30.00, $row['affiliate_commissions'], 0.001);
        $this->assertEqualsWithDelta(0.0, $row['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $row['platform_margin'], 0.001);
    }

    public function test_fbm_row_nets_the_refund_and_the_reversed_credit(): void
    {
        $row = $this->row($this->reportRows(), 'fbm', 'AED', $this->s->country->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(9.00, $row['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(0.45, $row['influencer_vat'], 0.001);
        $this->assertEqualsWithDelta(1.50, $row['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(0.90, $row['payouts_credited'], 0.001);
        $this->assertEqualsWithDelta(0.40, $row['payouts_reversed'], 0.001);
        $this->assertEqualsWithDelta(0.50, $row['payouts_net'], 0.001);
        $this->assertEqualsWithDelta(4.70, $row['refunds'], 0.001, 'fee + VAT + marketing given back to the vendor');
        $this->assertEqualsWithDelta(0.20, $row['refunds_vat'], 0.001);
        // 9.00 - 0.50 + 1.50 - (4.70 - 0.20) = 5.50 ; VAT liability 0.45 - 0.20
        $this->assertEqualsWithDelta(5.50, $row['platform_margin'], 0.001);
        $this->assertEqualsWithDelta(0.25, $row['vat_liability'], 0.001);
    }

    public function test_total_row_is_the_sum_of_the_types_per_country_and_currency(): void
    {
        $rows = $this->reportRows();
        $total = $this->row($rows, 'total', 'AED', $this->s->country->id);

        $this->assertEqualsWithDelta(27.00, $total['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(1.35, $total['influencer_vat'], 0.001);
        $this->assertEqualsWithDelta(3.79 + 1.50, $total['influencer_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(4.90, $total['affiliate_marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(18.19 + 5.50 + 4.90, $total['platform_margin'], 0.001);
        $this->assertEqualsWithDelta(
            $total['platform_margin'],
            $total['influencer_fees'] - $total['payouts_net'] + $total['marketing_fees'] - $total['refunds_net_of_vat'],
            0.001
        );
    }

    public function test_currencies_and_countries_are_never_blended(): void
    {
        $rows = $this->reportRows();
        $sar = $this->row($rows, 'total', 'SAR', $this->sarCountry->id);
        $aed = $this->row($rows, 'total', 'AED', $this->s->country->id);

        $this->assertEqualsWithDelta(10.00, $sar['influencer_fees'], 0.001);
        $this->assertEqualsWithDelta(1.50, $sar['influencer_vat'], 0.001);
        $this->assertEqualsWithDelta(2.00, $sar['marketing_fees'], 0.001);
        $this->assertEqualsWithDelta(3.00, $sar['payouts_net'], 0.001);
        $this->assertEqualsWithDelta(10.00 - 3.00 + 2.00, $sar['platform_margin'], 0.001);
        $this->assertEqualsWithDelta(27.00, $aed['influencer_fees'], 0.001, 'AED unaffected by SAR');

        $this->assertNull($this->row($rows, 'fbm', 'SAR'));
        $currencies = array_unique(array_column($rows, 'currency'));
        sort($currencies);
        $this->assertSame(['AED', 'SAR'], $currencies);
    }

    public function test_filters_by_country_currency_and_type(): void
    {
        $this->assertSame(['SAR'], array_values(array_unique(array_column($this->reportRows(['currency' => 'SAR']), 'currency'))));
        $this->assertSame(['fbn', 'total'], array_column($this->reportRows(['currency' => 'SAR']), 'type'));

        $fbm = $this->reportRows(['type' => 'fbm']);
        $this->assertSame(['fbm', 'total'], array_column($fbm, 'type'));
        $this->assertEqualsWithDelta(9.00, $fbm[1]['influencer_fees'], 0.001, 'total row of a type filter only sums that type');

        $this->assertCount(0, $this->reportRows(['country_id' => (string) Str::uuid()]));
    }

    public function test_snapshots_are_immune_to_later_setting_changes(): void
    {
        MarketingFeeCountrySetting::query()->update(['fixed_amount' => 99, 'fixed_percentage' => 50]);
        MarketerCommissionCountrySetting::query()->update(['influencer_fee_share_pct' => 90]);
        MarketerInfluencerFeeCountrySetting::query()->update(['fee_per_influencer' => 500]);

        $row = $this->row($this->reportRows(), 'fbn', 'AED', $this->s->country->id);
        $this->assertEqualsWithDelta(18.19, $row['platform_margin'], 0.001);
        $this->assertEqualsWithDelta(3.79, $row['marketing_fees'], 0.001);
    }

    public function test_date_range_only_counts_events_in_the_period(): void
    {
        $service = app(MarketingFeeReportService::class);
        $past = $service->platformReport(now()->subYear()->startOfMonth(), now()->subYear()->endOfMonth());
        $this->assertSame([], $past['rows']);

        // A refund dated after the collection lands in its own period only.
        $this->travel(40)->days();
        $later = $service->platformReport(now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame([], $later['rows']);
    }

    public function test_financial_report_service_exposes_the_lines_and_leaves_summary_and_net_untouched(): void
    {
        $service = app(FinancialReportService::class);
        $lines = $service->marketingFeeLines(now()->startOfMonth(), now());
        $this->assertNotEmpty($lines);

        $summary = $service->summaryForPeriod(now()->startOfMonth(), now());
        $this->assertArrayNotHasKey('marketing_fees', $summary);
        $this->assertEqualsWithDelta(
            $service->platformNetForPeriod(now()->startOfMonth(), now()),
            $summary['net'],
            0.0001,
            'the ledger anchor still equals the summary net'
        );
    }

    public function test_json_endpoint_returns_marketing_rows_per_country_currency_and_type(): void
    {
        $res = $this->actingAs($this->financeAdmin(), 'admin')
            ->getJson(route('admin.reports.financial.data', [
                'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(),
            ]));

        $res->assertOk()->assertJsonStructure(['rows', 'marketing' => ['rows']]);
        $rows = collect($res->json('marketing.rows'));
        $fbn = $rows->first(fn ($r) => $r['type'] === 'fbn' && $r['currency'] === 'AED');
        $this->assertEqualsWithDelta(18.19, $fbn['platform_margin'], 0.001);
        $this->assertSame(MarketingFeeType::Fbn->label(), $fbn['type_label']);
        $this->assertEqualsWithDelta(10.00, $rows->first(fn ($r) => $r['type'] === 'total' && $r['currency'] === 'SAR')['influencer_fees'], 0.001);
    }

    public function test_marketing_csv_export_has_one_row_per_country_currency_and_type(): void
    {
        $res = $this->actingAs($this->financeAdmin(), 'admin')
            ->get(route('admin.reports.financial.export', [
                'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(),
                'section' => 'marketing', 'format' => 'csv',
            ]));

        $res->assertOk();
        $csv = $res->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertSame('Country', $lines[0][0]);
        $this->assertContains('Platform margin', $lines[0]);

        $fbn = collect($lines)->first(fn ($l) => ($l[2] ?? '') === MarketingFeeType::Fbn->label() && ($l[1] ?? '') === 'AED');
        $this->assertSame('18.00', $fbn[3]);
        $this->assertSame('0.90', $fbn[4]);
        $this->assertSame('3.79', $fbn[7]);
        $this->assertSame('3.60', $fbn[8]);
        $this->assertSame('18.19', $fbn[12]);

        $sar = collect($lines)->first(fn ($l) => ($l[1] ?? '') === 'SAR' && ($l[2] ?? '') === __('common.total'));
        $this->assertSame('10.00', $sar[3]);
    }

    public function test_page_renders_the_marketing_section(): void
    {
        $this->actingAs($this->financeAdmin(), 'admin')
            ->get(route('admin.reports.financial.index'))
            ->assertOk()
            ->assertSee('marketing-fees-section', false);
    }
}
