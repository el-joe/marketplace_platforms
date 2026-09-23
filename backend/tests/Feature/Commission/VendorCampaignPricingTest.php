<?php

namespace Tests\Feature\Commission;

use App\Models\MarketerCommissionRule;
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
}
