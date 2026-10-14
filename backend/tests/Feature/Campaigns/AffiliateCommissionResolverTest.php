<?php

namespace Tests\Feature\Campaigns;

use App\Models\Category;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Services\AffiliateCommissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** Plan 1.3 / D3 / D11: profile rule -> category default (affiliates only, ancestor fallback) -> 0. */
class AffiliateCommissionResolverTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private AffiliateCommissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->resolver = app(AffiliateCommissionResolver::class);
    }

    private function categoryDefault(string $mode, float $rate, float $flat, ?Category $category = null): MarketerCommissionCountrySetting
    {
        return MarketerCommissionCountrySetting::create([
            'category_id' => ($category ?? $this->s->category)->id, 'country_id' => $this->s->country->id, 'currency' => 'AED',
            'affiliate_commission_mode' => $mode, 'affiliate_commission_rate' => $rate, 'affiliate_commission_flat' => $flat,
        ]);
    }

    private function profileRule(string $marketerId, string $mode, float $rate, float $flat, ?Category $category = null): MarketerCommissionRule
    {
        return MarketerCommissionRule::create([
            'marketer_id' => $marketerId, 'scope' => 'products',
            'category_type' => $category ? Category::class : null, 'category_id' => $category?->id,
            'commission_mode' => $mode, 'commission_rate' => $rate, 'commission_flat_amount' => $flat,
        ]);
    }

    private function amount($marketer, float $lineTotal = 200, int $qty = 2, ?Category $category = null): array
    {
        return $this->resolver->resolve($marketer, $this->s->country->id, ($category ?? $this->s->category)->id, $lineTotal, $qty);
    }

    public function test_affiliate_default_fixed_is_flat_times_quantity(): void
    {
        $this->categoryDefault('fixed', 0, 7);

        $r = $this->amount(F::affiliate($this->s), 200, 3);

        $this->assertSame(21.0, $r['amount']);
        $this->assertSame('category_default', $r['source']);
    }

    public function test_affiliate_default_percentage_is_a_share_of_the_line_total(): void
    {
        $this->categoryDefault('percentage', 10, 0);

        $this->assertSame(20.0, $this->amount(F::affiliate($this->s), 200, 2)['amount']);
    }

    public function test_affiliate_default_both_adds_percentage_and_flat_per_unit(): void
    {
        $this->categoryDefault('both', 10, 3);

        $this->assertSame(26.0, $this->amount(F::affiliate($this->s), 200, 2)['amount']);
    }

    public function test_a_mode_ignores_the_part_it_does_not_use(): void
    {
        $this->categoryDefault('percentage', 10, 99);

        $this->assertSame(20.0, $this->amount(F::affiliate($this->s), 200, 2)['amount']);
    }

    public function test_the_marketer_profile_rule_beats_the_category_default(): void
    {
        $this->categoryDefault('percentage', 10, 0);
        $affiliate = F::affiliate($this->s);
        $this->profileRule($affiliate->id, 'percentage', 15, 0, $this->s->category);

        $r = $this->amount($affiliate, 200, 2);

        $this->assertSame(30.0, $r['amount']);
        $this->assertSame('marketer_rule', $r['source']);
        // Another affiliate without a rule still gets the default.
        $this->assertSame(20.0, $this->amount(F::affiliate($this->s), 200, 2)['amount']);
    }

    public function test_category_default_falls_back_to_the_nearest_ancestor(): void
    {
        $parent = Category::create([
            'id' => (string) Str::uuid(), 'name_en' => 'Parent', 'name_ar' => 'اب', 'slug' => 'parent-'.Str::lower(Str::random(6)),
            'is_active' => true, 'is_visible' => true,
        ]);
        $this->s->category->update(['parent_id' => $parent->id]);
        $this->categoryDefault('fixed', 0, 4, $parent);

        $this->assertSame(8.0, $this->amount(F::affiliate($this->s), 100, 2)['amount']);

        // The child's own setting wins over the ancestor's.
        $this->categoryDefault('fixed', 0, 6);
        $this->assertSame(12.0, $this->amount(F::affiliate($this->s), 100, 2)['amount']);
    }

    public function test_profile_rule_falls_back_to_the_ancestor_category_rule(): void
    {
        $parent = Category::create([
            'id' => (string) Str::uuid(), 'name_en' => 'Parent', 'name_ar' => 'اب', 'slug' => 'parent-'.Str::lower(Str::random(6)),
            'is_active' => true, 'is_visible' => true,
        ]);
        $this->s->category->update(['parent_id' => $parent->id]);
        $this->categoryDefault('percentage', 10, 0);
        $affiliate = F::affiliate($this->s);
        $this->profileRule($affiliate->id, 'fixed', 0, 5, $parent);

        $r = $this->amount($affiliate, 100, 2);

        $this->assertSame(10.0, $r['amount']);
        $this->assertSame('marketer_rule', $r['source']);
    }

    public function test_nothing_configured_is_zero(): void
    {
        $r = $this->amount(F::affiliate($this->s));

        $this->assertSame(0.0, $r['amount']);
        $this->assertSame('none', $r['source']);
    }

    public function test_an_influencer_never_gets_the_category_default_only_a_profile_rule(): void
    {
        $this->categoryDefault('both', 10, 5);
        MarketerCommissionCountrySetting::where('category_id', $this->s->category->id)->update(['influencer_fee_share_pct' => 20]);
        $influencer = F::influencer($this->s);

        $this->assertSame(0.0, $this->amount($influencer, 200, 2)['amount']);

        $this->profileRule($influencer->id, 'fixed', 0, 8, $this->s->category);
        $r = $this->amount($influencer, 200, 2);
        $this->assertSame(16.0, $r['amount']);
        $this->assertSame('marketer_rule', $r['source']);
    }
}
