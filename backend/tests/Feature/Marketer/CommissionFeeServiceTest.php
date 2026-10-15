<?php

namespace Tests\Feature\Marketer;

use App\Enums\CommissionVertical;
use App\Models\Category;
use App\Models\Country;
use App\Models\Marketer;
use App\Services\Marketer\CommissionFeeService;
use App\Services\Marketer\MarketerDefaultCommissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\TestCase;

/** Commission fee resolution (profile category -> profile vertical -> platform default) and the default resolver fallback. */
class CommissionFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Marketer $marketer;

    private CommissionFeeService $fees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create();
        $this->marketer = Marketer::create([
            'name' => 'M', 'email' => 'f-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'country_id' => $this->country->id, 'global_status' => 'active', 'approved_at' => now(),
        ]);
        $this->fees = app(CommissionFeeService::class);
    }

    public function test_the_platform_default_applies_when_the_profile_has_no_override(): void
    {
        V::defaults($this->country, 'product', feePct: 10, feeFixed: 2);

        $detail = $this->fees->resolveDetailed($this->marketer, 'product');

        $this->assertSame([10.0, 2.0], [$detail['percentage'], $detail['fixed']]);
        $this->assertSame(['default', 'default'], [$detail['percentage_source'], $detail['fixed_source']]);
        $this->assertSame(['gross' => 100.0, 'fee' => 12.0, 'net' => 88.0], $this->fees->apply(100, 10, 2));
    }

    public function test_nothing_configured_means_no_fee(): void
    {
        $detail = $this->fees->resolveDetailed($this->marketer, CommissionVertical::Travel);

        $this->assertSame([0.0, 0.0, 'none'], [$detail['percentage'], $detail['fixed'], $detail['percentage_source']]);
    }

    public function test_the_category_override_beats_the_vertical_override_beats_the_default_field_by_field(): void
    {
        $category = Category::factory()->create();
        V::defaults($this->country, 'product', feePct: 10, feeFixed: 2);
        V::rule($this->marketer, 'product', rate: 8, feePct: 7, feeFixed: 1);
        V::rule($this->marketer, 'product', rate: 8, feePct: 3, categoryId: $category->id);

        $forCategory = $this->fees->resolveDetailed($this->marketer, 'product', $category->id);
        $this->assertSame([3.0, 1.0], [$forCategory['percentage'], $forCategory['fixed']]);
        $this->assertSame(['profile_category', 'profile_vertical'], [$forCategory['percentage_source'], $forCategory['fixed_source']]);

        $elsewhere = $this->fees->resolveDetailed($this->marketer, 'product', Category::factory()->create()->id);
        $this->assertSame([7.0, 1.0], [$elsewhere['percentage'], $elsewhere['fixed']]);
    }

    public function test_an_override_of_zero_is_an_override_not_a_blank(): void
    {
        V::defaults($this->country, 'product', feePct: 10, feeFixed: 2);
        V::rule($this->marketer, 'product', rate: 8, feePct: 0, feeFixed: 0);

        $this->assertSame([0.0, 0.0], $this->fees->resolve($this->marketer, 'product'));
    }

    public function test_overrides_of_one_vertical_do_not_leak_into_another(): void
    {
        V::defaults($this->country, 'classified', feePct: 4);
        V::rule($this->marketer, 'product', rate: 8, feePct: 50);

        $this->assertSame([4.0, 0.0], $this->fees->resolve($this->marketer, 'classified'));
    }

    public function test_the_fee_never_exceeds_the_commission_and_the_net_is_never_negative(): void
    {
        $this->assertSame(['gross' => 5.0, 'fee' => 5.0, 'net' => 0.0], $this->fees->apply(5, 20, 100));
        $this->assertSame(['gross' => 0.0, 'fee' => 0.0, 'net' => 0.0], $this->fees->apply(0, 20, 100));
    }

    public function test_default_resolver_prefers_the_category_row_then_the_vertical_row_then_zero(): void
    {
        $category = Category::factory()->create();
        V::defaults($this->country, 'product', influencerFixed: 200, feePct: 5);
        V::defaults($this->country, 'product', influencerFixed: 300, categoryId: $category->id);
        $resolver = new MarketerDefaultCommissionResolver;

        $this->assertSame(['amount' => 300.0, 'source' => 'category'], $resolver->influencerFixed($this->country->id, 'product', $category->id));
        $this->assertSame(['amount' => 200.0, 'source' => 'vertical'], $resolver->influencerFixed($this->country->id, 'product', Category::factory()->create()->id));
        $this->assertSame(['amount' => 0.0, 'source' => 'none'], $resolver->influencerFixed($this->country->id, 'travel'));
        // The category row carries no fee, so the vertical-wide fee still applies to it.
        $this->assertSame(5.0, $resolver->commissionFee($this->country->id, 'product', $category->id)['percentage']);
    }

    public function test_default_resolver_treats_an_explicit_zero_category_amount_as_a_free_override(): void
    {
        $category = Category::factory()->create();
        V::defaults($this->country, 'product', influencerFixed: 200);
        V::defaults($this->country, 'product', influencerFixed: 0, categoryId: $category->id);

        $this->assertSame(0.0, (new MarketerDefaultCommissionResolver)->influencerFixedAmount($this->country->id, 'product', $category->id));
    }

    public function test_default_resolver_is_per_country_and_needs_a_country(): void
    {
        $other = Country::factory()->create();
        V::defaults($this->country, 'classified', influencerFixed: 90);
        $resolver = new MarketerDefaultCommissionResolver;

        $this->assertSame(90.0, $resolver->influencerFixedAmount($this->country->id, CommissionVertical::Classified));
        $this->assertSame(0.0, $resolver->influencerFixedAmount($other->id, CommissionVertical::Classified));
        $this->assertSame(0.0, $resolver->influencerFixedAmount(null, CommissionVertical::Classified));
        $this->assertSame('none', $resolver->commissionFee(null, 'classified')['source']);
    }
}
