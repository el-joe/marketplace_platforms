<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerCommissionCountrySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** P3: category edit page > Marketers tab = influencer fee share % + affiliate default (mode / % / flat) per country. */
class CategoryMarketersTabTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('en');
        foreach (['vendors.assigned_only', 'categories.view', 'categories.edit', 'marketer_commission_settings.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->country = Country::factory()->create(['is_active' => true]);
        $this->category = Category::factory()->create();
    }

    private function admin(array $permissions): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function editor(): Admin
    {
        return $this->admin(['categories.view', 'categories.edit', 'marketer_commission_settings.edit']);
    }

    private function save(array $data, ?Admin $admin = null)
    {
        return $this->actingAs($admin ?? $this->editor(), 'admin')->post(
            route('admin.categories.marketer-commission.update', $this->category->id),
            ['country_id' => $this->country->id, 'currency' => 'AED'] + $data
        );
    }

    private function setting(): ?MarketerCommissionCountrySetting
    {
        return MarketerCommissionCountrySetting::where('category_id', $this->category->id)->where('country_id', $this->country->id)->first();
    }

    public function test_tab_shows_the_new_fields_with_clear_labels_and_no_old_amount_inputs(): void
    {
        MarketerCommissionCountrySetting::create([
            'category_id' => $this->category->id, 'country_id' => $this->country->id, 'currency' => 'AED',
            'influencer_fee_share_pct' => 20, 'affiliate_commission_mode' => 'both', 'affiliate_commission_rate' => 7.5, 'affiliate_commission_flat' => 3,
        ]);

        $html = $this->actingAs($this->editor(), 'admin')
            ->get(route('admin.categories.edit', $this->category->id))
            ->assertOk()
            ->assertSee('data-testid="marketer-country-settings"', false)
            ->assertSee(__('admin.categories.influencer_fee_share_pct'))
            ->assertSee(__('admin.categories.affiliate_default_title'))
            ->assertSee(__('admin.categories.affiliate_mode_both'))
            ->assertSee('data-field="influencer_fee_share_pct"', false)
            ->assertSee('value="20"', false)
            ->assertSee('value="7.5"', false)
            ->getContent();

        $this->assertStringNotContainsString('influencer_commission_'.'amount', $html);
        $this->assertStringNotContainsString('affiliate_commission_'.'amount', $html);
    }

    public function test_saves_fee_share_and_a_percentage_default_and_zeroes_the_unused_flat(): void
    {
        $this->save([
            'influencer_fee_share_pct' => 20, 'affiliate_commission_mode' => 'percentage',
            'affiliate_commission_rate' => 12.5, 'affiliate_commission_flat' => 99,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $s = $this->setting();
        $this->assertEquals(20, $s->influencer_fee_share_pct);
        $this->assertSame('percentage', $s->affiliate_commission_mode);
        $this->assertEquals(12.5, $s->affiliate_commission_rate);
        $this->assertEquals(0, $s->affiliate_commission_flat);
    }

    public function test_saves_fixed_and_both_modes_and_updates_the_same_row(): void
    {
        $this->save(['influencer_fee_share_pct' => 0, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_rate' => 50, 'affiliate_commission_flat' => 4.25]);
        $s = $this->setting();
        $this->assertSame('fixed', $s->affiliate_commission_mode);
        $this->assertEquals(0, $s->affiliate_commission_rate);
        $this->assertEquals(4.25, $s->affiliate_commission_flat);

        $this->save(['influencer_fee_share_pct' => 15, 'affiliate_commission_mode' => 'both', 'affiliate_commission_rate' => 10, 'affiliate_commission_flat' => 2]);
        $this->assertSame(1, MarketerCommissionCountrySetting::where('category_id', $this->category->id)->count());
        $s = $this->setting();
        $this->assertSame('both', $s->affiliate_commission_mode);
        $this->assertEquals(26.0, $s->affiliateAmountFor(200, 3)); // 10% of 200 + 2 x 3
    }

    public function test_rejects_out_of_range_percentages_and_unknown_modes(): void
    {
        $this->save(['influencer_fee_share_pct' => 101, 'affiliate_commission_mode' => 'percentage', 'affiliate_commission_rate' => 5])
            ->assertSessionHasErrors('influencer_fee_share_pct');
        $this->save(['influencer_fee_share_pct' => 10, 'affiliate_commission_mode' => 'percentage', 'affiliate_commission_rate' => 100.5])
            ->assertSessionHasErrors('affiliate_commission_rate');
        $this->save(['influencer_fee_share_pct' => 10, 'affiliate_commission_mode' => 'bogus'])
            ->assertSessionHasErrors('affiliate_commission_mode');
        $this->save(['influencer_fee_share_pct' => 10, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => -1])
            ->assertSessionHasErrors('affiliate_commission_flat');
        $this->assertNull($this->setting());
    }

    public function test_old_amount_fields_are_ignored(): void
    {
        $this->save([
            'influencer_commission_'.'amount' => 5000, 'affiliate_commission_'.'amount' => 5000,
            'influencer_fee_share_pct' => 10, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1, $this->setting()->affiliate_commission_flat);
    }

    public function test_json_save_used_by_the_tab_returns_the_message(): void
    {
        $this->actingAs($this->editor(), 'admin')->postJson(
            route('admin.categories.marketer-commission.update', $this->category->id),
            ['country_id' => $this->country->id, 'currency' => 'AED', 'influencer_fee_share_pct' => 20, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => 3]
        )->assertOk()->assertJsonPath('success', true);

        $this->postJson(
            route('admin.categories.marketer-commission.update', $this->category->id),
            ['country_id' => $this->country->id, 'currency' => 'AED', 'influencer_fee_share_pct' => 120, 'affiliate_commission_mode' => 'fixed']
        )->assertStatus(422)->assertJsonValidationErrors('influencer_fee_share_pct');
    }

    public function test_requires_the_marketer_commission_edit_permission(): void
    {
        $this->save(
            ['influencer_fee_share_pct' => 10, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => 1],
            $this->admin(['categories.view', 'categories.edit'])
        )->assertForbidden();

        $this->assertNull($this->setting());
    }
}
