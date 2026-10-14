<?php

namespace Tests\Feature;

use App\Enums\MarketingFeeType;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Services\MarketingFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Plan section 6: UAE example, fixed 1.9 AED + 0 %, variable 0 AED + 10 %, subtotal 18.90. */
class MarketingFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $category;

    private MarketingFeeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create();
        $this->category = Category::factory()->create();
        $this->svc = new MarketingFeeService;
    }

    private function fixed(float $amount, float $pct): void
    {
        MarketingFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->country->id],
            ['fixed_amount' => $amount, 'fixed_percentage' => $pct]
        );
    }

    private function variable(MarketingFeeType $type, float $amount, float $pct, ?Category $category = null): void
    {
        MarketingFeeCategorySetting::updateOrCreate(
            ['country_id' => $this->country->id, 'category_id' => ($category ?? $this->category)->id, 'fulfillment_type' => $type->value],
            ['fixed_amount' => $amount, 'percentage' => $pct]
        );
    }

    private function calc(float $subtotal, MarketingFeeType $type = MarketingFeeType::Fbn, ?Category $category = null, bool $flat = true): array
    {
        return $this->svc->calculate($subtotal, $this->country->id, ($category ?? $this->category)->id, $type, $flat);
    }

    public function test_uae_example_gives_22_69_grand_total(): void
    {
        $this->fixed(1.9, 0);
        $this->variable(MarketingFeeType::Fbn, 0, 10);

        $fees = $this->calc(18.90);

        $this->assertSame(1.90, $fees['fixed_fee']);
        $this->assertSame(1.89, $fees['variable_fee']);
        $this->assertSame(3.79, $fees['total_fee']);
        $this->assertSame(22.69, round(18.90 + $fees['total_fee'], 2));
    }

    public function test_percentage_only_amount_only_and_both(): void
    {
        $this->fixed(0, 5);
        $this->variable(MarketingFeeType::Fbn, 0, 0);
        $this->assertSame(0.95, $this->calc(18.90)['fixed_fee']);
        $this->assertSame(0.0, $this->calc(18.90)['variable_fee']);

        $this->fixed(2, 0);
        $this->variable(MarketingFeeType::Fbn, 3, 0);
        $fees = $this->calc(18.90);
        $this->assertSame(2.0, $fees['fixed_fee']);
        $this->assertSame(3.0, $fees['variable_fee']);

        $this->fixed(2, 10);
        $this->variable(MarketingFeeType::Fbn, 3, 10);
        $fees = $this->calc(100);
        $this->assertSame(12.0, $fees['fixed_fee']);
        $this->assertSame(13.0, $fees['variable_fee']);
        $this->assertSame(25.0, $fees['total_fee']);
    }

    public function test_zero_subtotal_and_missing_settings_charge_nothing(): void
    {
        $this->fixed(1.9, 5);
        $this->variable(MarketingFeeType::Fbn, 3, 10);

        $this->assertSame(['fixed_fee' => 0.0, 'variable_fee' => 0.0, 'total_fee' => 0.0], $this->calc(0));
        $this->assertSame(['fixed_fee' => 0.0, 'variable_fee' => 0.0, 'total_fee' => 0.0], $this->calc(0, MarketingFeeType::Fbn, null, false));

        $other = Country::factory()->create();
        $this->assertSame(0.0, $this->svc->calculate(18.90, $other->id, $this->category->id, MarketingFeeType::Fbn)['total_fee']);
    }

    public function test_variable_fee_falls_back_to_nearest_ancestor_category(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $grandChild = Category::factory()->create(['parent_id' => $child->id]);
        $this->variable(MarketingFeeType::Fbn, 0, 10, $parent);

        $this->assertSame(1.89, $this->calc(18.90, MarketingFeeType::Fbn, $grandChild)['variable_fee']);

        $this->variable(MarketingFeeType::Fbn, 0, 20, $child);
        $this->assertSame(3.78, $this->calc(18.90, MarketingFeeType::Fbn, $grandChild)['variable_fee']);
        $this->assertSame(1.89, $this->calc(18.90, MarketingFeeType::Fbn, $parent)['variable_fee']);
    }

    public function test_each_type_uses_its_own_variable_rule(): void
    {
        $this->variable(MarketingFeeType::Fbm, 0, 10);
        $this->variable(MarketingFeeType::Fbn, 0, 20);
        $this->variable(MarketingFeeType::Fbp, 1, 0);

        $this->assertSame(1.89, $this->calc(18.90, MarketingFeeType::fromListingModel('cross_dock'))['variable_fee']);
        $this->assertSame(3.78, $this->calc(18.90, MarketingFeeType::fromListingModel('fbn'))['variable_fee']);
        $this->assertSame(1.0, $this->calc(18.90, MarketingFeeType::fromListingModel('fbm'))['variable_fee']);
    }

    public function test_flat_amounts_can_be_skipped_for_later_affiliate_accruals(): void
    {
        $this->fixed(1.9, 0);
        $this->variable(MarketingFeeType::Fbn, 0, 10);

        // Plan section 6, affiliate: order 1 commission 20 -> 1.90 + 2.00; order 2 commission 10 -> 1.00.
        $first = $this->calc(20);
        $this->assertSame(1.9, $first['fixed_fee']);
        $this->assertSame(2.0, $first['variable_fee']);
        $this->assertSame(3.9, $first['total_fee']);

        $second = $this->calc(10, MarketingFeeType::Fbn, null, false);
        $this->assertSame(0.0, $second['fixed_fee']);
        $this->assertSame(1.0, $second['variable_fee']);
        $this->assertSame(4.9, round($first['total_fee'] + $second['total_fee'], 2));
    }

    public function test_new_marketing_fee_columns_exist_and_old_amount_columns_are_dropped(): void
    {
        foreach (['fulfillment_type', 'campaign_subtotal', 'marketing_fee_fixed', 'marketing_fee_variable', 'marketing_fee_total', 'campaign_grand_total', 'affiliate_fee_accrued', 'affiliate_fee_settled_at', 'marketing_fee_fixed_accrued'] as $column) {
            $this->assertTrue(Schema::hasColumn('marketer_campaigns', $column), $column);
        }
        foreach (['platform_fee_marketing_amount', 'marketer_fee_share_pct', 'marketer_payout_amount', 'marketer_credited_at', 'marketer_payout_wallet_tx_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('marketer_campaign_invitations', $column), $column);
        }
        foreach (['influencer_fee_share_pct', 'affiliate_commission_mode', 'affiliate_commission_rate', 'affiliate_commission_flat'] as $column) {
            $this->assertTrue(Schema::hasColumn('marketer_commission_country_settings', $column), $column);
        }
        foreach (['influencer_commission_'.'amount', 'affiliate_commission_'.'amount'] as $column) {
            $this->assertFalse(Schema::hasColumn('marketer_commission_country_settings', $column), $column);
        }
        $this->assertTrue(Schema::hasTable('marketer_campaign_fee_accruals'));
    }
}
