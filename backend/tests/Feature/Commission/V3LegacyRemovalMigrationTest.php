<?php

namespace Tests\Feature\Commission;

use App\Models\MarketerJob;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\TestCase;

/**
 * Smoke test of the destructive v3 migration 2026_10_16_900001: after the normal migrate the legacy structures are
 * gone, running it again changes nothing (every drop is guarded by hasTable / hasColumn), and the removed settings
 * keys are deleted.
 */
class V3LegacyRemovalMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = '2026_10_16_900001_drop_v3_legacy_marketer_commission_and_fee_structures.php';

    private const REMOVED_TABLES = [
        'marketer_campaign_fee_accruals', 'marketer_campaign_fee_settlements', 'marketer_campaign_tiered_rules',
        'marketer_campaign_category_rules', 'marketer_category_commissions', 'open_market_category_commissions',
        'marketer_commission_country_settings', 'marketer_influencer_fee_country_settings',
        'promotion_category_targets', 'marketer_promotion_fines', 'marketer_promotion_reminders',
        'travel_agency_campaign_offers',
    ];

    private const REMOVED_COLUMNS = [
        'marketer_profiles' => ['platform_cut_percentage', 'commission_discount_type', 'monthly_promotion_target'],
        'marketer_campaign_invitations' => ['marketer_fee_share_pct', 'marketer_payout_amount', 'marketer_clawback_amount', 'platform_fee_marketing_amount'],
        'marketer_campaign_conversions' => ['tiered_rule_id', 'platform_cut_amount', 'platform_cut_vat_amount', 'flash_sale_bonus_amount'],
        'marketer_campaigns' => ['affiliate_fee_accrued', 'affiliate_fee_settled_at', 'marketing_fee_fixed_accrued'],
        'payout_items' => ['affiliate_fee_settlement_id'],
        'flash_sale_marketer_invitations' => ['extra_commission_rate', 'extra_commission_mode', 'extra_commission_flat_amount'],
    ];

    private function migration(): object
    {
        return require database_path('migrations/'.self::FILE);
    }

    public function test_legacy_tables_and_columns_are_gone_after_migrating(): void
    {
        foreach (self::REMOVED_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "table {$table} should be dropped");
        }
        foreach (self::REMOVED_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertFalse(Schema::hasColumn($table, $column), "{$table}.{$column} should be dropped");
            }
        }
    }

    public function test_the_v3_structures_exist(): void
    {
        $this->assertTrue(Schema::hasTable('marketer_default_commissions'));
        $this->assertTrue(Schema::hasTable('marketing_fee_vertical_settings'));
        foreach (['gross_commission_amount', 'commission_fee_amount', 'net_commission_amount', 'conversion_type', 'travel_booking_id', 'classified_listing_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('marketer_campaign_conversions', $column), $column);
        }
        foreach (['influencer_fixed_amount', 'influencer_fixed_status'] as $column) {
            $this->assertTrue(Schema::hasColumn('marketer_campaign_invitations', $column), $column);
        }
        $this->assertTrue(MarketerJob::where('key', 'broker')->exists(), 'the broker job is seeded by migration');
    }

    public function test_running_it_again_is_a_no_op_and_removes_the_stale_settings(): void
    {
        F::setting('marketer_influencer_fee_payer', 'vendor');
        F::setting('marketer_referral_grace_days', 5);

        $migration = $this->migration();
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertSame(0, Setting::where('key', 'marketer_influencer_fee_payer')->count());
        $this->assertSame(1, Setting::where('key', 'marketer_referral_grace_days')->count(), 'unrelated settings are untouched');
        $this->assertFalse(Schema::hasTable('marketer_category_commissions'));
    }
}
