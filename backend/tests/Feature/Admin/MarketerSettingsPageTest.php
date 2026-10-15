<?php

namespace Tests\Feature\Admin;

use App\Enums\CommissionVertical;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerDefaultCommission;
use App\Models\Setting;
use App\Services\Marketer\CommissionFeeService;
use App\Services\Marketer\MarketerDefaultCommissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** R3: Marketer Settings tabs + the default-commission resolver and commission-fee service. */
class MarketerSettingsPageTest extends TestCase
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
        $this->country = Country::factory()->create(['is_active' => true, 'currency_code' => 'EGP']);
    }

    private function admin(bool $canEdit = true): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canEdit ? ['marketer_commission_settings.view', 'marketer_fee_settings.edit'] : ['marketer_commission_settings.view']);

        return $admin;
    }

    public function test_page_renders_the_four_tabs_in_both_languages_without_the_removed_sections(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->actingAs($this->admin(), 'admin')->get(route('admin.marketer-settings.index'))
                ->assertOk()
                ->assertSee('data-panel="influencer"', false)
                ->assertSee('data-panel="fees"', false)
                ->assertSee('data-panel="campaign"', false)
                ->assertSee('data-panel="general"', false)
                ->assertSee(__('marketer_settings.tabs.influencer'))
                ->assertDontSee('promotion-targets', false);
        }
    }

    public function test_influencer_defaults_are_saved_per_country_and_vertical_without_touching_fees(): void
    {
        MarketerDefaultCommission::create([
            'country_id' => $this->country->id, 'category_type' => 'product',
            'influencer_fixed_amount' => 1, 'commission_fee_percentage' => 10, 'commission_fee_fixed' => 2,
        ]);

        $this->actingAs($this->admin(), 'admin')->post(route('admin.marketer-settings.influencer-defaults.update'), [
            'amounts' => [$this->country->id => ['product' => '200', 'classified' => '', 'travel' => '75.5']],
        ])->assertSessionHasNoErrors();

        $product = MarketerDefaultCommission::forVertical($this->country->id, CommissionVertical::Product);
        $this->assertEquals(200, $product->influencer_fixed_amount);
        $this->assertEquals(10, (float) $product->commission_fee_percentage);
        $this->assertEquals(2, $product->commission_fee_fixed);
        $this->assertEquals(75.5, MarketerDefaultCommission::forVertical($this->country->id, CommissionVertical::Travel)->influencer_fixed_amount);
        $this->assertNull(MarketerDefaultCommission::forVertical($this->country->id, CommissionVertical::Classified));
    }

    public function test_commission_fees_are_saved_and_a_viewer_cannot_edit(): void
    {
        $payload = ['fees' => [$this->country->id => ['product' => ['percentage' => '10', 'fixed' => '2']]]];

        $this->actingAs($this->admin(canEdit: false), 'admin')
            ->post(route('admin.marketer-settings.commission-fees.update'), $payload)->assertForbidden();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.marketer-settings.commission-fees.update'), $payload)->assertSessionHasNoErrors();

        $row = MarketerDefaultCommission::forVertical($this->country->id, 'product');
        $this->assertEquals(10, (float) $row->commission_fee_percentage);
        $this->assertEquals(2, $row->commission_fee_fixed);
    }

    public function test_general_settings_are_validated_and_saved(): void
    {
        $this->actingAs($this->admin(), 'admin')->post(route('admin.marketer-settings.general.update'), [
            'marketer_referral_grace_days' => 5, 'marketer_payout_clearing_days' => 4,
            'marketer_attribution_window_days' => 30, 'marketer_peer_campaign_min_stock' => 2,
        ])->assertSessionHasNoErrors();

        Setting::flush();
        $this->assertSame([5, 4, 30, 2], [
            (int) setting('marketer_referral_grace_days'), (int) setting('marketer_payout_clearing_days'),
            (int) setting('marketer_attribution_window_days'), (int) setting('marketer_peer_campaign_min_stock'),
        ]);

        $this->post(route('admin.marketer-settings.general.update'), [
            'marketer_referral_grace_days' => 5, 'marketer_payout_clearing_days' => 4,
            'marketer_attribution_window_days' => 0, 'marketer_peer_campaign_min_stock' => 2,
        ])->assertSessionHasErrors('marketer_attribution_window_days');
    }

    public function test_resolver_prefers_the_category_row_and_falls_back_to_the_vertical_row(): void
    {
        $category = Category::factory()->create();
        MarketerDefaultCommission::create(['country_id' => $this->country->id, 'category_type' => 'product', 'influencer_fixed_amount' => 200, 'commission_fee_percentage' => 5]);
        MarketerDefaultCommission::create(['country_id' => $this->country->id, 'category_type' => 'product', 'category_id' => $category->id, 'influencer_fixed_amount' => 300]);

        $resolver = new MarketerDefaultCommissionResolver;
        $this->assertEquals(300, $resolver->influencerFixedAmount($this->country->id, 'product', $category->id));
        $this->assertEquals(200, $resolver->influencerFixedAmount($this->country->id, 'product', (string) \Illuminate\Support\Str::uuid()));
        $this->assertEquals(200, $resolver->influencerFixedAmount($this->country->id, CommissionVertical::Product));
        $this->assertEquals(0, $resolver->influencerFixedAmount($this->country->id, 'travel'));
        // The category row has no fee of its own, so the vertical-wide fee applies.
        $this->assertEquals(5, $resolver->commissionFee($this->country->id, 'product', $category->id)['percentage']);
    }

    public function test_commission_fee_service_uses_profile_override_then_default(): void
    {
        MarketerDefaultCommission::create([
            'country_id' => $this->country->id, 'category_type' => 'product',
            'commission_fee_percentage' => 10, 'commission_fee_fixed' => 2,
        ]);
        $marketer = Marketer::create([
            'name' => 'M', 'email' => 'f-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'country_id' => $this->country->id, 'global_status' => 'active', 'approved_at' => now(),
        ]);
        $service = app(CommissionFeeService::class);

        $this->assertSame([10.0, 2.0], $service->resolve($marketer, 'product'));
        $this->assertSame(['gross' => 100.0, 'fee' => 12.0, 'net' => 88.0], $service->apply(100, 10, 2));
        $this->assertSame(['gross' => 1.0, 'fee' => 1.0, 'net' => 0.0], $service->apply(1, 10, 2));

        MarketerCommissionRule::create([
            'marketer_id' => $marketer->id, 'scope' => 'products', 'commission_mode' => 'percentage',
            'commission_rate' => 8, 'fee_percentage' => 3,
        ]);
        // Percentage overridden on the profile, fixed fee still comes from the default.
        $this->assertSame([3.0, 2.0], $service->resolve($marketer, 'product'));
    }
}
