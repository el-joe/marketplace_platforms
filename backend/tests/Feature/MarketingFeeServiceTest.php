<?php

namespace Tests\Feature;

use App\Enums\CommissionVertical;
use App\Enums\MarketingFeeType;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\MarketingFeeVerticalSetting;
use App\Services\MarketingFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v3 campaign fee (paid by the vendor / agency): fixed fee + variable fee, each = amount | % | both, on the campaign
 * total (Max Commission Budget). Plan section 9: subtotal 500, fixed 10 + 2 % = 20, variable 5 + 1 % = 10 -> 30.
 */
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

    private function calc(float $subtotal, MarketingFeeType $type = MarketingFeeType::Fbn, ?Category $category = null): array
    {
        return $this->svc->calculate($subtotal, $this->country->id, ($category ?? $this->category)->id, $type);
    }

    public function test_plan_example_subtotal_500_gives_fixed_20_variable_10_total_30(): void
    {
        $this->fixed(10, 2);
        $this->variable(MarketingFeeType::Fbn, 5, 1);

        $fees = $this->calc(500);

        $this->assertSame(20.0, $fees['fixed_fee']);
        $this->assertSame(10.0, $fees['variable_fee']);
        $this->assertSame(30.0, $fees['total_fee']);
    }

    public function test_each_part_can_be_amount_only_percentage_only_or_both(): void
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

    public function test_uae_example_rounds_half_up_to_two_decimals(): void
    {
        $this->fixed(1.9, 0);
        $this->variable(MarketingFeeType::Fbn, 0, 10);

        $fees = $this->calc(18.90);

        $this->assertSame(1.90, $fees['fixed_fee']);
        $this->assertSame(1.89, $fees['variable_fee']);
        $this->assertSame(3.79, $fees['total_fee']);
    }

    public function test_zero_total_and_missing_settings_charge_nothing(): void
    {
        $this->fixed(1.9, 5);
        $this->variable(MarketingFeeType::Fbn, 3, 10);

        $this->assertSame(['fixed_fee' => 0.0, 'variable_fee' => 0.0, 'total_fee' => 0.0], $this->calc(0));

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

    public function test_each_fulfilment_type_uses_its_own_variable_rule(): void
    {
        $this->variable(MarketingFeeType::Fbm, 0, 10);
        $this->variable(MarketingFeeType::Fbn, 0, 20);
        $this->variable(MarketingFeeType::Fbp, 1, 0);

        $this->assertSame(1.89, $this->calc(18.90, MarketingFeeType::fromListingModel('cross_dock'))['variable_fee']);
        $this->assertSame(3.78, $this->calc(18.90, MarketingFeeType::fromListingModel('fbn'))['variable_fee']);
        $this->assertSame(1.0, $this->calc(18.90, MarketingFeeType::fromListingModel('fbm'))['variable_fee']);
    }

    public function test_classified_and_travel_use_the_vertical_variable_setting(): void
    {
        $this->fixed(10, 2);
        MarketingFeeVerticalSetting::create(['country_id' => $this->country->id, 'vertical' => 'classified', 'fixed_amount' => 5, 'percentage' => 1]);
        MarketingFeeVerticalSetting::create(['country_id' => $this->country->id, 'vertical' => 'travel', 'fixed_amount' => 0, 'percentage' => 4]);

        $classified = $this->svc->calculateForVertical(500, $this->country->id, CommissionVertical::Classified);
        $this->assertSame([20.0, 10.0, 30.0], [$classified['fixed_fee'], $classified['variable_fee'], $classified['total_fee']]);

        $travel = $this->svc->calculateForVertical(500, $this->country->id, CommissionVertical::Travel);
        $this->assertSame([20.0, 20.0, 40.0], [$travel['fixed_fee'], $travel['variable_fee'], $travel['total_fee']]);

        $this->assertSame(0.0, $this->svc->calculateForVertical(0, $this->country->id, CommissionVertical::Travel)['total_fee']);
    }
}
