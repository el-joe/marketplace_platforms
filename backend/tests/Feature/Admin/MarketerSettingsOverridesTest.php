<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerDefaultCommission;
use App\Models\MarketingFeeVerticalSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Marketer Settings (v3): per-category influencer overrides and the classified / travel campaign-fee tab. Tabs themselves: MarketerSettingsPageTest. */
class MarketerSettingsOverridesTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['vendors.assigned_only', 'marketer_commission_settings.view', 'marketer_fee_settings.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->country = Country::factory()->create(['is_active' => true]);
    }

    private function admin(bool $canEdit = true): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canEdit ? ['marketer_commission_settings.view', 'marketer_fee_settings.edit'] : ['marketer_commission_settings.view']);

        return $admin;
    }

    public function test_a_category_override_is_saved_updated_and_deleted(): void
    {
        $category = Category::factory()->create();
        $payload = ['country_id' => $this->country->id, 'category_type' => 'product', 'category_id' => $category->id, 'influencer_fixed_amount' => '250'];

        $this->actingAs($this->admin(), 'admin')->post(route('admin.marketer-settings.influencer-overrides.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin(), 'admin')->post(route('admin.marketer-settings.influencer-overrides.store'), array_merge($payload, ['influencer_fixed_amount' => '300.5']))->assertSessionHasNoErrors();

        $row = MarketerDefaultCommission::where('category_id', $category->id)->sole();
        $this->assertEquals(300.5, $row->influencer_fixed_amount);

        $this->actingAs($this->admin(), 'admin')->delete(route('admin.marketer-settings.influencer-overrides.destroy', $row))->assertSessionHasNoErrors();
        $this->assertSame(0, MarketerDefaultCommission::where('category_id', $category->id)->count());
    }

    public function test_an_override_needs_an_existing_category_a_valid_amount_and_edit_rights(): void
    {
        $payload = ['country_id' => $this->country->id, 'category_type' => 'product', 'category_id' => (string) \Illuminate\Support\Str::uuid(), 'influencer_fixed_amount' => '10'];

        $this->actingAs($this->admin(), 'admin')->post(route('admin.marketer-settings.influencer-overrides.store'), $payload)->assertSessionHasErrors('category_id');

        $category = Category::factory()->create();
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.marketer-settings.influencer-overrides.store'), array_merge($payload, ['category_id' => $category->id, 'influencer_fixed_amount' => '-1']))
            ->assertSessionHasErrors('influencer_fixed_amount');

        $this->actingAs($this->admin(canEdit: false), 'admin')
            ->post(route('admin.marketer-settings.influencer-overrides.store'), array_merge($payload, ['category_id' => $category->id]))
            ->assertForbidden();
        $this->assertSame(0, MarketerDefaultCommission::count());
    }

    public function test_the_vertical_campaign_fee_is_saved_for_classified_and_travel_only(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-vertical'), [
            'country_id' => $this->country->id, 'vertical' => 'travel', 'fixed_amount' => '5', 'percentage' => '2.5',
        ])->assertSessionHasNoErrors();

        $setting = MarketingFeeVerticalSetting::where('country_id', $this->country->id)->where('vertical', 'travel')->sole();
        $this->assertEquals(5, $setting->fixed_amount);
        $this->assertEquals(2.5, (float) $setting->percentage);

        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-vertical'), [
            'country_id' => $this->country->id, 'vertical' => 'product', 'fixed_amount' => '5', 'percentage' => '1',
        ])->assertSessionHasErrors('vertical');
    }
}
