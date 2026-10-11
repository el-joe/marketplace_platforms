<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CategoryCommissionTier;
use App\Models\CountryCategory;
use App\Models\FlashSale;
use App\Models\FlashSaleMarketerInvitation;
use App\Models\MarketerCategoryCommission;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Models\MarketplaceShippingRule;
use App\Services\Admin\Commissions\CommissionTabRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The /commissions tabs post to the project's existing write endpoints. These tests pin the contract the page
 * relies on: authorisation, persistence, validation, and that redirect-style writes return to the same tab.
 */
class CommissionsInPageEditingTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function admin(array $permissions): Admin
    {
        foreach (array_merge($permissions, ['vendors.assigned_only']) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    // ── Campaign amounts (previously unusable: parameter/route-binding mismatch) ─────────────────

    public function test_updates_amounts_of_a_campaign_pending_admin_review(): void
    {
        $this->s->marketerCampaign->update(['status' => 'pending_admin']);

        $this->actingAs($this->admin(['marketer_campaigns.view', 'marketer_campaigns.approve']), 'admin')
            ->patchJson(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), [
                'marketer_commission_amount' => 700, 'platform_commission_amount' => 300,
            ])->assertOk()->assertJsonPath('success', true);

        $campaign = $this->s->marketerCampaign->fresh();
        $this->assertSame(700, (int) $campaign->marketer_commission_amount);
        $this->assertSame(300, (int) $campaign->platform_commission_amount);
    }

    public function test_campaign_amount_route_authorises_the_admin_guard_even_when_the_default_guard_is_web(): void
    {
        $this->s->marketerCampaign->update(['status' => 'pending_admin']);
        $admin = $this->admin(['marketer_campaigns.view', 'marketer_campaigns.approve']);
        $this->actingAs($admin, 'admin');
        // actingAs() switches the default guard to "admin", which hid a production bug: real requests use "web".
        Auth::shouldUse('web');

        $this->patchJson(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), [
            'marketer_commission_amount' => 700, 'platform_commission_amount' => 300,
        ])->assertOk();

        $this->assertSame(700, (int) $this->s->marketerCampaign->fresh()->marketer_commission_amount);
    }

    public function test_refuses_to_change_amounts_of_a_campaign_that_is_not_pending(): void
    {
        $before = (int) $this->s->marketerCampaign->marketer_commission_amount;

        $this->actingAs($this->admin(['marketer_campaigns.view', 'marketer_campaigns.approve']), 'admin')
            ->patchJson(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), [
                'marketer_commission_amount' => 700, 'platform_commission_amount' => 300,
            ])->assertUnprocessable();

        $this->assertSame($before, (int) $this->s->marketerCampaign->fresh()->marketer_commission_amount);
    }

    public function test_rejects_zero_marketer_amount_and_negative_platform_amount(): void
    {
        $this->s->marketerCampaign->update(['status' => 'pending_admin']);

        $this->actingAs($this->admin(['marketer_campaigns.view', 'marketer_campaigns.approve']), 'admin')
            ->patchJson(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), [
                'marketer_commission_amount' => 0, 'platform_commission_amount' => -1,
            ])->assertUnprocessable()->assertJsonValidationErrors(['marketer_commission_amount', 'platform_commission_amount']);
    }

    public function test_forbids_campaign_amount_update_without_approve_permission(): void
    {
        $this->s->marketerCampaign->update(['status' => 'pending_admin']);

        $this->actingAs($this->admin(['marketer_campaigns.view']), 'admin')
            ->patchJson(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), [
                'marketer_commission_amount' => 700, 'platform_commission_amount' => 300,
            ])->assertForbidden();
    }

    // ── Category country overrides and price tiers ───────────────────────────────────────────────

    public function test_saves_a_country_override_and_blank_fields_inherit_as_null(): void
    {
        $this->actingAs($this->admin(['categories.view', 'categories.edit']), 'admin')
            ->putJson(route('admin.categories.country-commission.update', $this->s->category->id), [
                'country_id' => $this->s->country->id, 'commission_fbp_pct' => '8.5', 'commission_fbp_fixed' => '40', 'commission_fbn_pct' => '', 'commission_fbn_fixed' => '',
            ])->assertOk();

        $row = CountryCategory::where('category_id', $this->s->category->id)->where('country_id', $this->s->country->id)->firstOrFail();
        $this->assertSame('8.50', (string) $row->commission_fbp_pct);
        $this->assertSame(40, (int) $row->commission_fbp_fixed);
        $this->assertNull($row->commission_fbn_pct);
        $this->assertNull($row->commission_fbn_fixed);
    }

    public function test_rejects_country_override_percentage_above_100_and_unknown_country(): void
    {
        $admin = $this->admin(['categories.view', 'categories.edit']);
        $url = route('admin.categories.country-commission.update', $this->s->category->id);

        $this->actingAs($admin, 'admin')->putJson($url, ['country_id' => $this->s->country->id, 'commission_fbp_pct' => 101])
            ->assertUnprocessable()->assertJsonValidationErrors('commission_fbp_pct');
        $this->actingAs($admin, 'admin')->putJson($url, ['country_id' => '11111111-1111-4111-8111-111111111111'])
            ->assertUnprocessable()->assertJsonValidationErrors('country_id');
    }

    public function test_forbids_country_override_and_tier_writes_without_categories_edit(): void
    {
        $viewer = $this->admin(['categories.view']);

        $this->actingAs($viewer, 'admin')->putJson(route('admin.categories.country-commission.update', $this->s->category->id), ['country_id' => $this->s->country->id])->assertForbidden();
        $this->actingAs($viewer, 'admin')->postJson(route('admin.categories.commission-tiers.store', $this->s->category->id), ['price_from' => 0, 'commission_rate' => 5])->assertForbidden();

        $this->assertSame(0, CountryCategory::where('category_id', $this->s->category->id)->count());
        $this->assertSame(0, CategoryCommissionTier::where('category_id', $this->s->category->id)->count());
    }

    public function test_creates_updates_and_deletes_a_price_tier_and_rejects_overlap(): void
    {
        $admin = $this->admin(['categories.view', 'categories.edit']);
        $store = route('admin.categories.commission-tiers.store', $this->s->category->id);

        $this->actingAs($admin, 'admin')->postJson($store, ['price_from' => 0, 'price_to' => 1000, 'commission_rate' => 9, 'min_commission' => 5])->assertOk();
        $tier = CategoryCommissionTier::where('category_id', $this->s->category->id)->firstOrFail();
        $this->assertSame(5, (int) $tier->min_commission);

        $this->actingAs($admin, 'admin')->postJson($store, ['price_from' => 500, 'price_to' => 2000, 'commission_rate' => 7])
            ->assertUnprocessable()->assertJsonValidationErrors('price_from');
        $this->assertSame(1, CategoryCommissionTier::where('category_id', $this->s->category->id)->count());

        $this->actingAs($admin, 'admin')->putJson(route('admin.categories.commission-tiers.update', [$this->s->category->id, $tier->id]), ['price_from' => 0, 'price_to' => 1000, 'commission_rate' => 11])->assertOk();
        $this->assertSame('11.00', (string) $tier->fresh()->commission_rate);

        $this->actingAs($admin, 'admin')->deleteJson(route('admin.categories.commission-tiers.destroy', [$this->s->category->id, $tier->id]))->assertOk();
        $this->assertModelMissing($tier);
    }

    // ── Country × category marketer amounts (redirect-back endpoint) ─────────────────────────────

    public function test_saves_country_amounts_and_returns_to_the_same_tab(): void
    {
        $tabUrl = route('admin.commissions.index', ['tab' => 'country-amounts']);

        $this->actingAs($this->admin(['categories.view', 'marketer_commission_settings.view', 'marketer_commission_settings.edit']), 'admin')
            ->from($tabUrl)
            ->post(route('admin.categories.marketer-commission.update', $this->s->category->id), [
                'country_id' => $this->s->country->id, 'influencer_commission_amount' => 120, 'affiliate_commission_amount' => 60, 'currency' => 'AED',
            ])->assertRedirect($tabUrl)->assertSessionHas('success');

        $row = MarketerCommissionCountrySetting::where('category_id', $this->s->category->id)->firstOrFail();
        $this->assertSame([120, 60], [(int) $row->influencer_commission_amount, (int) $row->affiliate_commission_amount]);
    }

    public function test_country_amount_validation_failure_returns_to_the_same_tab_with_errors_and_input(): void
    {
        $tabUrl = route('admin.commissions.index', ['tab' => 'country-amounts']);

        $this->actingAs($this->admin(['categories.view', 'marketer_commission_settings.view', 'marketer_commission_settings.edit']), 'admin')
            ->from($tabUrl)
            ->post(route('admin.categories.marketer-commission.update', $this->s->category->id), [
                'country_id' => $this->s->country->id, 'influencer_commission_amount' => -5, 'affiliate_commission_amount' => 60, 'currency' => 'AED',
            ])->assertRedirect($tabUrl)->assertSessionHasErrors('influencer_commission_amount')->assertSessionHasInput('affiliate_commission_amount', 60);

        $this->assertSame(0, MarketerCommissionCountrySetting::count());
    }

    public function test_forbids_country_amounts_without_edit_permission(): void
    {
        $this->actingAs($this->admin(['categories.view', 'marketer_commission_settings.view']), 'admin')
            ->post(route('admin.categories.marketer-commission.update', $this->s->category->id), [
                'country_id' => $this->s->country->id, 'influencer_commission_amount' => 1, 'affiliate_commission_amount' => 1, 'currency' => 'AED',
            ])->assertForbidden();

        $this->assertSame(0, MarketerCommissionCountrySetting::count());
    }

    // ── Marketer category rules (resolver table + legacy mirror) ─────────────────────────────────

    public function test_saves_marketer_rule_to_resolver_table_mirrors_it_and_returns_to_the_tab(): void
    {
        $tabUrl = route('admin.commissions.index', ['tab' => 'marketer-rules']);

        $this->actingAs($this->admin(['marketers.view', 'marketers.manage']), 'admin')
            ->from($tabUrl)
            ->post(route('admin.marketers.category-commissions.store', $this->s->marketer->id), [
                'scope' => 'products', 'category_id' => $this->s->category->id, 'commission_rate' => 10, 'commission_flat_amount' => 5, 'commission_min_amount' => 2,
            ])->assertRedirect($tabUrl)->assertSessionHas('success');

        $rule = MarketerCommissionRule::where('marketer_id', $this->s->marketer->id)->firstOrFail();
        $this->assertSame('both', $rule->commission_mode);
        $this->assertSame(2, (int) $rule->commission_min_amount);
        $this->assertSame(1, MarketerCategoryCommission::where('marketer_id', $this->s->marketer->id)->count(), 'legacy mirror written');
    }

    public function test_marketer_rule_requires_a_rate_or_a_fixed_amount_and_keeps_the_user_on_the_tab(): void
    {
        $tabUrl = route('admin.commissions.index', ['tab' => 'marketer-rules']);

        $this->actingAs($this->admin(['marketers.view', 'marketers.manage']), 'admin')
            ->from($tabUrl)
            ->post(route('admin.marketers.category-commissions.store', $this->s->marketer->id), ['scope' => 'products', 'commission_rate' => 0])
            ->assertRedirect($tabUrl)->assertSessionHasErrors('commission_rate');

        $this->assertSame(0, MarketerCommissionRule::count());
    }

    public function test_deleting_a_marketer_rule_removes_the_rule_and_its_legacy_mirror(): void
    {
        $admin = $this->admin(['marketers.view', 'marketers.manage']);
        $this->actingAs($admin, 'admin')->post(route('admin.marketers.category-commissions.store', $this->s->marketer->id), ['scope' => 'products', 'commission_rate' => 12])->assertSessionHasNoErrors();
        $rule = MarketerCommissionRule::where('marketer_id', $this->s->marketer->id)->firstOrFail();

        $this->actingAs($admin, 'admin')->delete(route('admin.marketers.category-commissions.destroy', [$this->s->marketer->id, $rule->id]))->assertRedirect();

        $this->assertModelMissing($rule);
        $this->assertSame(0, MarketerCategoryCommission::where('marketer_id', $this->s->marketer->id)->count());
    }

    public function test_forbids_marketer_rule_writes_without_marketers_manage(): void
    {
        $viewer = $this->admin(['marketers.view']);

        $this->actingAs($viewer, 'admin')->post(route('admin.marketers.category-commissions.store', $this->s->marketer->id), ['scope' => 'products', 'commission_rate' => 5])->assertForbidden();

        $this->assertSame(0, MarketerCommissionRule::count());
    }

    // ── Shipping rule ────────────────────────────────────────────────────────────────────────────

    public function test_saves_shipping_rule_values_keeping_commission_and_extra_fee_separate(): void
    {
        $this->actingAs($this->admin(['admin_listings.view', 'admin_listings.edit']), 'admin')
            ->postJson(route('admin.admin-listings.save-shipping-rule', $this->s->adminListing->id), [
                'commission_type' => 'mixed', 'commission_value' => '4.5', 'extra_delivery_fee' => 25,
                'requires_special_vehicle' => 0, 'requires_refrigeration' => 1, 'max_weight_kg' => '', 'max_dimensions_cm' => '', 'special_handling_notes' => '',
            ])->assertOk();

        $rule = MarketplaceShippingRule::where('admin_listing_id', $this->s->adminListing->id)->firstOrFail();
        $this->assertSame('mixed', $rule->commission_type->value);
        $this->assertSame(25, (int) $rule->extra_delivery_fee);
        $this->assertTrue((bool) $rule->requires_refrigeration);
    }

    public function test_rejects_unknown_shipping_commission_type_and_negative_values(): void
    {
        $this->actingAs($this->admin(['admin_listings.view', 'admin_listings.edit']), 'admin')
            ->postJson(route('admin.admin-listings.save-shipping-rule', $this->s->adminListing->id), ['commission_type' => 'bogus', 'commission_value' => -1, 'extra_delivery_fee' => -3])
            ->assertUnprocessable()->assertJsonValidationErrors(['commission_type', 'commission_value', 'extra_delivery_fee']);

        $this->assertSame(0, MarketplaceShippingRule::count());
    }

    public function test_forbids_shipping_rule_save_without_admin_listings_edit(): void
    {
        $this->actingAs($this->admin(['admin_listings.view']), 'admin')
            ->postJson(route('admin.admin-listings.save-shipping-rule', $this->s->adminListing->id), ['commission_type' => 'fixed', 'commission_value' => 1])
            ->assertForbidden();
    }

    // ── Flash-sale marketer bonus (invitation) ───────────────────────────────────────────────────

    private function flashSale(): FlashSale
    {
        return FlashSale::create([
            'country_id' => null, 'name_en' => 'Mega', 'name_ar' => 'ميغا', 'status' => 'live',
            'submission_opens_at' => now()->subDays(5), 'submission_closes_at' => now()->subDays(4), 'review_deadline_at' => now()->subDays(3),
            'sale_starts_at' => now()->subHour(), 'sale_ends_at' => now()->addHours(4), 'min_discount_pct' => 10,
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);
    }

    public function test_invites_marketer_to_flash_sale_with_a_bonus_in_both_mode(): void
    {
        Notification::fake();
        $sale = $this->flashSale();

        $this->actingAs($this->admin(['flash_sales.view', 'flash_sales.edit']), 'admin')
            ->postJson(route('admin.flash-sales.invite-marketers', $sale->id), [
                'marketer_ids' => [$this->s->marketer->id], 'extra_commission_rate' => 5, 'extra_commission_flat_amount' => 20,
            ])->assertOk()->assertJsonPath('count', 1);

        $invitation = FlashSaleMarketerInvitation::where('flash_sale_id', $sale->id)->firstOrFail();
        $this->assertSame('both', $invitation->extra_commission_mode->value ?? $invitation->extra_commission_mode);
        $this->assertSame(20, (int) $invitation->extra_commission_flat_amount);
    }

    public function test_flash_sale_invitation_rejects_rate_above_100_and_requires_marketers(): void
    {
        $sale = $this->flashSale();

        $this->actingAs($this->admin(['flash_sales.view', 'flash_sales.edit']), 'admin')
            ->postJson(route('admin.flash-sales.invite-marketers', $sale->id), ['extra_commission_rate' => 150])
            ->assertUnprocessable()->assertJsonValidationErrors(['marketer_ids', 'extra_commission_rate']);
    }

    public function test_forbids_flash_sale_invitations_without_flash_sales_edit(): void
    {
        $sale = $this->flashSale();

        $this->actingAs($this->admin(['flash_sales.view']), 'admin')
            ->postJson(route('admin.flash-sales.invite-marketers', $sale->id), ['marketer_ids' => [$this->s->marketer->id]])
            ->assertForbidden();

        $this->assertSame(0, FlashSaleMarketerInvitation::count());
    }

    // ── Page: editors appear only for admins who may use them ────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: string}> tab, permissions that unlock the editor, markup that only the editor renders
     */
    public static function editorMarkers(): array
    {
        return [
            'marketer-rules' => ['marketer-rules', ['marketers.view', 'marketers.manage'], 'id="marketer-rule-form"'],
            'vendor-flat-rate' => ['vendor-flat-rate', ['vendors.view', 'vendors.edit'], 'name="commission_rate"'],
            'vendor-discount' => ['vendor-discount', ['vendors.view', 'vendors.edit'], 'name="commission_discount_type"'],
            'acquisition' => ['acquisition', ['vendors.view', 'vendors.edit'], 'id="acq-assign-form"'],
            'country-amounts' => ['country-amounts', ['marketer_commission_settings.view', 'marketer_commission_settings.edit'], 'id="ca-form"'],
            'flash-bonus' => ['flash-bonus', ['flash_sales.view', 'flash_sales.edit'], 'id="fb-form"'],
        ];
    }

    #[DataProvider('editorMarkers')]
    public function test_editor_is_rendered_for_an_authorised_admin_and_hidden_from_a_view_only_admin(string $tab, array $permissions, string $marker): void
    {
        $vendor = $this->s->vendor;
        $vendor->update(['commission_rate' => 4, 'commission_discount_type' => 'flat', 'commission_discount_flat' => 10]);
        $url = route('admin.commissions.index', ['tab' => $tab, 'vendor' => $vendor->id]);

        $this->actingAs($this->admin($permissions), 'admin')->withSession(['locale' => 'en'])->get($url)->assertOk()->assertSee($marker, false);

        $viewOnly = array_values(array_filter($permissions, fn (string $p) => str_ends_with($p, '.view')));
        $this->actingAs($this->admin($viewOnly), 'admin')->withSession(['locale' => 'en'])->get($url)->assertOk()->assertDontSee($marker, false);
    }

    public function test_platform_rules_editor_lists_overrides_and_tiers_for_the_chosen_category(): void
    {
        CountryCategory::create(['category_id' => $this->s->category->id, 'country_id' => $this->s->country->id, 'commission_fbp_pct' => 6, 'commission_fbp_fixed' => 15]);
        CategoryCommissionTier::create(['category_id' => $this->s->category->id, 'price_from' => 0, 'price_to' => 500, 'commission_rate' => 3, 'min_commission' => 1, 'sort_order' => 0]);

        $this->actingAs($this->admin(['categories.view', 'categories.edit']), 'admin')->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => 'platform-rules', 'category' => $this->s->category->id]))
            ->assertOk()
            ->assertSee('id="pr-override-form"', false)
            ->assertSee('id="pr-tier-form"', false)
            ->assertSee(route('admin.categories.commission-tiers.store', $this->s->category->id), false)
            ->assertSeeText('Base FBP/FBN values are read-only');
    }

    public function test_campaign_tab_offers_amount_editing_only_for_pending_campaigns(): void
    {
        $this->s->marketerCampaign->update(['status' => 'pending_admin']);
        $admin = $this->admin(['marketer_campaigns.view', 'marketer_campaigns.approve', 'marketer_campaigns.reject']);
        $url = route('admin.commissions.index', ['tab' => 'campaigns']);

        $this->actingAs($admin, 'admin')->get($url)->assertOk()
            ->assertSee(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), false)
            ->assertSee(route('admin.marketer-campaigns.approve', $this->s->marketerCampaign->id), false);

        $this->s->marketerCampaign->update(['status' => 'active']);
        $this->actingAs($admin, 'admin')->get($url)->assertOk()
            ->assertDontSee(route('admin.marketer-campaigns.commission.update', $this->s->marketerCampaign->id), false);
    }

    public function test_registry_marks_exactly_the_tabs_with_in_page_editors_as_editable(): void
    {
        $editable = collect(CommissionTabRegistry::tabs())->filter(fn ($t) => $t['access'] === CommissionTabRegistry::ACCESS_EDITABLE)->keys()->sort()->values()->all();

        $this->assertSame(
            ['acquisition', 'campaigns', 'country-amounts', 'flash-bonus', 'marketer-rules', 'platform-rules', 'shipping-rules', 'vendor-discount', 'vendor-flat-rate', 'vendor-rules'],
            $editable
        );
        foreach ($editable as $key) {
            $this->assertTrue(view()->exists('admin.commissions.tabs.'.$key), "editable tab $key has no editor partial");
        }
    }
}
