<?php

namespace Tests\Feature\Marketer;

use App\Enums\MarketerType;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Services\Marketer\CommissionResult;
use App\Services\Marketer\MarketerCommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * v3 plan section 9 worked examples through the single commission entry point.
 *
 *  influencer  default fixed 200 per campaign (never per sale), fee 5 % + 1 -> 11 -> net 189
 *  affiliate   8 % of 1,000 = 80, fee 10 % + 2 = 10 -> net 70
 *  broker      15 + 5 % of 1,000 = 65, fee 10 % + 2 = 8.5 -> net 56.5
 */
class MarketerCommissionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCommissionCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->calc = app(MarketerCommissionCalculator::class);
    }

    private function compute($marketer, $basis = 1000, int $qty = 1, ?MarketerCampaignInvitation $invitation = null): CommissionResult
    {
        return $this->calc->compute($marketer, $this->s->marketerCampaign->fresh(), $basis, $qty, $invitation);
    }

    public function test_influencer_earns_one_default_fixed_amount_per_campaign_whatever_was_sold(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200, feePct: 5, feeFixed: 1);
        $influencer = F::influencer($this->s);

        $threeUnits = $this->compute($influencer, 3000, 3);
        $threeHundredUnits = $this->compute($influencer, 300000, 300);

        foreach ([$threeUnits, $threeHundredUnits] as $result) {
            $this->assertSame(MarketerType::Influencer, $result->marketerType);
            $this->assertSame(200.0, $result->gross);
            $this->assertSame(11.0, $result->fee);
            $this->assertSame(189.0, $result->net);
            $this->assertSame(MarketerCommissionCalculator::SOURCE_DEFAULT, $result->ruleSource);
            $this->assertTrue($result->perCampaign);
        }
    }

    public function test_influencer_profile_fixed_rule_beats_the_default_and_the_fee_still_applies(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200, feePct: 5, feeFixed: 1);
        $influencer = F::influencer($this->s);
        V::rule($influencer, 'product', flat: 300);

        $result = $this->compute($influencer);

        $this->assertSame(300.0, $result->gross);
        $this->assertSame(MarketerCommissionCalculator::SOURCE_PROFILE, $result->ruleSource);
        $this->assertSame(16.0, $result->fee);
        $this->assertSame(284.0, $result->net);
    }

    public function test_influencer_amount_snapshotted_on_the_invitation_ignores_later_setting_changes(): void
    {
        $default = V::defaults($this->s->country, 'product', influencerFixed: 200);
        $influencer = F::influencer($this->s);
        $invitation = new MarketerCampaignInvitation([
            'influencer_fixed_amount' => 150,
            'influencer_fixed_status' => MarketerCampaignInvitation::FIXED_RESERVED,
        ]);

        $default->update(['influencer_fixed_amount' => 999]);
        $result = $this->compute($influencer, 0, 1, $invitation);

        $this->assertSame(150.0, $result->gross);
        $this->assertSame(MarketerCommissionCalculator::SOURCE_SNAPSHOT, $result->ruleSource);
    }

    public function test_influencer_without_any_configured_amount_earns_nothing(): void
    {
        $result = $this->compute(F::influencer($this->s));

        $this->assertTrue($result->isEmpty());
        $this->assertSame(MarketerCommissionCalculator::SOURCE_NONE, $result->ruleSource);
    }

    public function test_affiliate_earns_a_percentage_only_even_if_a_fixed_part_is_stored(): void
    {
        V::defaults($this->s->country, 'product', feePct: 10, feeFixed: 2);
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'product', rate: 8, flat: 50);

        $result = $this->compute($affiliate, 1000);

        $this->assertSame(MarketerType::Affiliate, $result->marketerType);
        $this->assertSame(80.0, $result->gross);
        $this->assertSame(10.0, $result->fee);
        $this->assertSame(70.0, $result->net);
        $this->assertSame(CommissionResult::MODE_PERCENTAGE, $result->mode);
    }

    public function test_affiliate_without_a_rule_earns_nothing(): void
    {
        $result = $this->compute(F::affiliate($this->s));

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0.0, $result->net);
    }

    public function test_broker_earns_fixed_plus_percentage(): void
    {
        V::defaults($this->s->country, 'product', feePct: 10, feeFixed: 2);
        $broker = V::broker($this->s);
        V::rule($broker, 'product', rate: 5, flat: 15);

        $result = $this->compute($broker, 1000);

        $this->assertSame(MarketerType::Broker, $result->marketerType);
        $this->assertSame(65.0, $result->gross);
        $this->assertSame(8.5, $result->fee);
        $this->assertSame(56.5, $result->net);
        $this->assertSame(CommissionResult::MODE_BOTH, $result->mode);
    }

    public function test_broker_fixed_part_is_per_sold_item_and_either_part_may_be_missing(): void
    {
        $broker = V::broker($this->s);
        $rule = V::rule($broker, 'product', rate: 5, flat: 15);

        $this->assertSame(80.0, $this->compute($broker, 1000, 2)->gross); // 15 x 2 + 5 % x 1,000

        $rule->update(['commission_mode' => 'fixed', 'commission_rate' => 0]);
        $this->assertSame(CommissionResult::MODE_FIXED, $this->compute($broker, 1000)->mode);
        $this->assertSame(15.0, $this->compute($broker, 1000)->gross);

        $rule->update(['commission_mode' => 'percentage', 'commission_rate' => 5, 'commission_flat_amount' => null]);
        $this->assertSame(50.0, $this->compute($broker, 1000)->gross);
    }

    public function test_a_marketer_who_is_both_broker_and_affiliate_acts_as_broker(): void
    {
        $broker = V::broker($this->s);
        $affiliateJob = MarketerJob::firstOrCreate(['key' => 'affiliate'], ['name_ar' => 'affiliate', 'name_en' => 'affiliate', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $broker->id, 'marketer_job_id' => $affiliateJob->id]);

        $this->assertSame(MarketerType::Broker, $this->calc->effectiveType($broker->fresh()));
    }

    public function test_the_category_rule_beats_the_vertical_wide_rule(): void
    {
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'product', rate: 8);
        V::rule($affiliate, 'product', rate: 12, categoryId: $this->s->category->id);

        $this->assertSame(120.0, $this->compute($affiliate, 1000)->gross);
    }

    public function test_the_profile_fee_override_replaces_only_the_fields_it_sets(): void
    {
        V::defaults($this->s->country, 'product', feePct: 10, feeFixed: 2);
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'product', rate: 8, feePct: 25);

        $result = $this->compute($affiliate, 1000);

        $this->assertSame(25.0, $result->feePercentage);
        $this->assertSame(2.0, $result->feeFixed);
        $this->assertSame(22.0, $result->fee); // 25 % x 80 + 2
        $this->assertSame(58.0, $result->net);
    }

    public function test_the_net_is_never_negative(): void
    {
        V::defaults($this->s->country, 'product', feePct: 0, feeFixed: 500);
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'product', rate: 1);

        $result = $this->compute($affiliate, 1000); // gross 10, fee capped at 10

        $this->assertSame(10.0, $result->gross);
        $this->assertSame(10.0, $result->fee);
        $this->assertSame(0.0, $result->net);
    }

    public function test_with_gross_shortens_the_commission_and_recomputes_the_fee(): void
    {
        V::defaults($this->s->country, 'product', feePct: 10, feeFixed: 2);
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'product', rate: 8);

        $capped = $this->compute($affiliate, 1000)->withGross(40);

        $this->assertSame([40.0, 6.0, 34.0], [$capped->gross, $capped->fee, $capped->net]);
    }
}
