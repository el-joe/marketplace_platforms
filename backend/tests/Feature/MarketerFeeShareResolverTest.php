<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerCommissionCountrySetting;
use App\Services\MarketerFeeShareResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketerFeeShareResolverTest extends TestCase
{
    use RefreshDatabase;

    private function setting(Country $country, Category $category, float $pct): void
    {
        MarketerCommissionCountrySetting::create([
            'country_id' => $country->id, 'category_id' => $category->id, 'currency' => 'AED',
            'influencer_fee_share_pct' => $pct,
        ]);
    }

    public function test_share_of_a_9_fee_at_20_percent_is_1_80(): void
    {
        $country = Country::factory()->create();
        $category = Category::factory()->create();
        $this->setting($country, $category, 20);

        $split = (new MarketerFeeShareResolver)->split(9, $country->id, $category->id);

        $this->assertSame(1.8, $split['marketer_payout']);
        $this->assertSame(7.2, $split['platform_keeps']);
        $this->assertSame(20.0, $split['share_pct']);
        $this->assertSame(3.6, 2 * $split['marketer_payout']);
    }

    public function test_share_falls_back_to_ancestor_and_defaults_to_zero(): void
    {
        $country = Country::factory()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $resolver = new MarketerFeeShareResolver;

        $this->assertSame(0.0, $resolver->sharePct($country->id, $child->id));
        $this->assertSame(['share_pct' => 0.0, 'marketer_payout' => 0.0, 'platform_keeps' => 9.0], $resolver->split(9, $country->id, $child->id));

        $this->setting($country, $parent, 12.5);
        $this->assertSame(12.5, $resolver->sharePct($country->id, $child->id));
        $this->assertSame(1.13, $resolver->split(9, $country->id, $child->id)['marketer_payout']);
    }

    public function test_default_new_columns_keep_old_rows_neutral(): void
    {
        $country = Country::factory()->create();
        $category = Category::factory()->create();
        $row = MarketerCommissionCountrySetting::create([
            'country_id' => $country->id, 'category_id' => $category->id, 'currency' => 'AED',
        ])->fresh();

        $this->assertSame(0.0, (float) $row->influencer_fee_share_pct);
        $this->assertSame('percentage', $row->affiliate_commission_mode);
        $this->assertSame(0, $row->affiliate_commission_flat);
    }
}
