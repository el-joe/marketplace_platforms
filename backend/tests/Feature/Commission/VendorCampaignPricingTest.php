<?php

namespace Tests\Feature\Commission;

use App\Models\Category;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class VendorCampaignPricingTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $va = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'V', 'email' => Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        $va->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'vendor']));
        $this->actingAs($va, 'vendor');
    }

    private function pricing(array $extra = [])
    {
        return $this->getJson(route('partner.listings.campaign-pricing', [
            'product_id' => $this->s->product->id, 'country_id' => $this->s->country->id,
        ] + $extra));
    }

    public function test_both_mode_estimate(): void
    {
        MarketerCommissionRule::create(['scope' => 'products', 'commission_mode' => 'both', 'commission_rate' => 10, 'commission_flat_amount' => 5]);
        $this->pricing(['price' => 100, 'quantity' => 1])->assertOk()
            ->assertJsonPath('commission_mode', 'both')->assertJsonPath('estimated_amount', 15);
    }

    public function test_missing_rule_is_null_and_zero(): void
    {
        $this->pricing(['price' => 100])->assertOk()
            ->assertJsonPath('commission_mode', null)->assertJsonPath('estimated_amount', 0);
    }

    public function test_returns_the_fee_share_and_the_affiliate_default_with_ancestor_fallback(): void
    {
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 9, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
        $parent = Category::create([
            'id' => (string) Str::uuid(), 'name_en' => 'P', 'name_ar' => 'ب', 'slug' => 'p-'.Str::lower(Str::random(6)),
            'is_active' => true, 'is_visible' => true,
        ]);
        $this->s->category->update(['parent_id' => $parent->id]);
        MarketerCommissionCountrySetting::create([
            'category_id' => $parent->id, 'country_id' => $this->s->country->id, 'currency' => 'AED',
            'influencer_fee_share_pct' => 20, 'affiliate_commission_mode' => 'both', 'affiliate_commission_rate' => 10, 'affiliate_commission_flat' => 3,
        ]);

        $this->pricing(['price' => 200, 'quantity' => 2])->assertOk()
            ->assertJsonPath('influencer_fee_share_pct', 20)
            ->assertJsonPath('influencer_fee_share_amount', 1.8)
            ->assertJsonPath('affiliate_commission_mode', 'both')
            ->assertJsonPath('affiliate_commission_rate', 10)
            ->assertJsonPath('affiliate_commission_flat', 3)
            ->assertJsonPath('affiliate_estimated_amount', 26)
            ->assertJsonMissingPath('influencer_commission_'.'amount')
            ->assertJsonMissingPath('affiliate_commission_'.'amount');
    }

    public function test_without_a_category_setting_the_new_fields_are_zero(): void
    {
        $this->pricing(['price' => 100])->assertOk()
            ->assertJsonPath('influencer_fee_share_pct', 0)
            ->assertJsonPath('affiliate_commission_mode', null)
            ->assertJsonPath('affiliate_estimated_amount', 0);
    }
}
