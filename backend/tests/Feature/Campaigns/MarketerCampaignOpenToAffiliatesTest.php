<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerCampaign;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\VendorAdmin;
use App\Services\MarketerCampaignQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** Vendor create form: "Open to all registered affiliates" + decimal platform fee / VAT preview. */
class MarketerCampaignOpenToAffiliatesTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private VendorAdmin $vendorAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
        Admin::factory()->create();
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 0);
    }

    private function store(array $payload)
    {
        return $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), [
            'vendor_listing_id' => $this->s->vendorListingFbn->id,
        ] + $payload);
    }

    public function test_open_to_affiliates_invites_every_eligible_affiliate_and_no_influencer_is_needed(): void
    {
        $a = F::affiliate($this->s);
        $b = F::affiliate($this->s);

        $this->store(['open_to_affiliates' => 1])->assertSessionMissing('error')->assertSessionHasNoErrors()->assertRedirect();

        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $campaign->requested_marketer_vendor_ids);
    }

    public function test_open_to_affiliates_adds_to_hand_picked_influencers_and_ignores_other_countries(): void
    {
        $influencer = F::influencer($this->s);
        $affiliate = F::affiliate($this->s);
        $foreign = F::affiliate($this->s);
        $foreign->update(['country_id' => Country::factory()->create()->id]);

        $this->store(['open_to_affiliates' => 1, 'marketer_ids' => [$influencer->id]])->assertSessionHasNoErrors();

        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $this->assertEqualsCanonicalizing([$influencer->id, $affiliate->id], $campaign->requested_marketer_vendor_ids);
    }

    public function test_without_the_checkbox_or_a_pick_nothing_is_created(): void
    {
        F::affiliate($this->s);

        $this->store([])->assertSessionHasErrors('marketer_ids');
        $this->assertSame(0, MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->count());
    }

    public function test_checkbox_is_enabled_and_counts_affiliates_when_some_exist(): void
    {
        F::affiliate($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee(__('partner.marketer_campaigns.open_to_affiliates_desc', ['count' => 1]));
    }

    public function test_fee_and_vat_preview_accepts_a_decimal_fee_and_the_budget_accepts_cents(): void
    {
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 2, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
        $inf = F::influencer($this->s);

        $quote = $this->actingAs($this->vendorAdmin, 'vendor')
            ->getJson(route('partner.listings.marketer-fee', ['listing_id' => $this->s->vendorListingFbn->id, 'marketer_ids' => [$inf->id]]))
            ->assertOk()->json();
        $this->assertEqualsWithDelta(0.1, $quote['fee_vat_per_influencer'], 0.00001);
        $this->assertEqualsWithDelta(2.1, $quote['total_fee_with_vat'], 0.00001);

        $this->store(['marketer_ids' => [$inf->id], 'max_commission_budget' => '1250.75'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1250.75, (float) MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole()->max_commission_budget, 0.00001);

        $this->store(['marketer_ids' => [$inf->id], 'max_commission_budget' => '10.123'])->assertSessionHasErrors('max_commission_budget');
    }

    public function test_checkbox_is_enabled_without_affiliates_and_create_form_shows_wallet_balance(): void
    {
        $html = $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/name="open_to_affiliates"[^>]*disabled/', $html);
        $this->assertStringContainsString('data-testid="wallet-balance"', $html);
    }

    public function test_admin_campaign_page_lists_requested_marketers_before_approval(): void
    {
        $influencer = F::influencer($this->s);
        $this->store(['marketer_ids' => [$influencer->id]])->assertSessionHasNoErrors();
        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $this->assertSame([$influencer->id], $campaign->requested_marketer_vendor_ids);

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketer_campaigns.view', 'guard_name' => 'admin']);
        $admin = Admin::first();
        $admin->givePermissionTo('marketer_campaigns.view');
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="requested-marketers"', false)
            ->assertSee($influencer->name);
    }

    public function test_admin_campaign_page_shows_the_fee_share_and_affiliate_default_of_the_category(): void
    {
        $influencer = F::influencer($this->s);
        $this->store(['marketer_ids' => [$influencer->id]])->assertSessionHasNoErrors();
        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        MarketerCommissionCountrySetting::create([
            'category_id' => $this->s->category->id, 'country_id' => $campaign->country_id, 'currency' => 'AED',
            'influencer_fee_share_pct' => 12.5, 'affiliate_commission_mode' => 'both', 'affiliate_commission_rate' => 8, 'affiliate_commission_flat' => 3,
        ]);

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketer_campaigns.view', 'guard_name' => 'admin']);
        $admin = Admin::first();
        $admin->givePermissionTo('marketer_campaigns.view');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee(__('admin.marketer_campaigns.influencer_commission_label'))
            ->assertSee('12.5%')
            ->assertSee('8%')
            ->assertSee('3 AED');
    }

    public function test_quote_uses_the_category_defaults_of_a_parent_category_and_its_own_when_present(): void
    {
        $parent = Category::create([
            'id' => (string) Str::uuid(), 'name_en' => 'Abaya', 'name_ar' => 'عباية', 'slug' => 'abaya-'.Str::lower(Str::random(6)),
            'is_active' => true, 'is_visible' => true,
        ]);
        $this->s->category->update(['parent_id' => $parent->id]);
        $influencer = F::influencer($this->s);
        $affiliate = F::affiliate($this->s);
        $countryId = $this->s->vendorListingFbn->country_id;
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $countryId],
            ['fee_per_influencer' => 9, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );

        MarketerCommissionCountrySetting::create([
            'category_id' => $parent->id, 'country_id' => $countryId, 'currency' => 'AED',
            'influencer_fee_share_pct' => 20, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => 5,
        ]);

        $quote = app(MarketerCampaignQuoteService::class)->quote($this->s->vendorListingFbn->fresh(), [$influencer->id, $affiliate->id]);
        $rows = collect($quote['rows'])->keyBy('id');
        // Influencer rows show the fee-share payout (20% of the 9.00 fee), affiliates the per-unit commission.
        $this->assertEquals(1.8, $rows[$influencer->id]['commission']['amount']);
        $this->assertSame('fee_share', $rows[$influencer->id]['commission']['source']);
        $this->assertEquals(20, $rows[$influencer->id]['fee_share_pct']);
        $this->assertEquals(1.8, $rows[$influencer->id]['marketer_payout']);
        $this->assertEquals(7.2, $rows[$influencer->id]['platform_keeps']);
        $this->assertEquals(0, $rows[$influencer->id]['per_sale_commission']['amount']);
        $this->assertEquals(5, $rows[$affiliate->id]['commission']['amount']);
        $this->assertSame('category_default', $rows[$affiliate->id]['commission']['source']);

        MarketerCommissionCountrySetting::create([
            'category_id' => $this->s->category->id, 'country_id' => $countryId, 'currency' => 'AED',
            'influencer_fee_share_pct' => 50, 'affiliate_commission_mode' => 'percentage', 'affiliate_commission_rate' => 10,
        ]);
        $listing = $this->s->vendorListingFbn->fresh();
        $quote = app(MarketerCampaignQuoteService::class)->quote($listing, [$influencer->id, $affiliate->id]);
        $rows = collect($quote['rows'])->keyBy('id');
        $this->assertEquals(4.5, $rows[$influencer->id]['commission']['amount']);
        $this->assertEquals(round((float) $listing->price * 0.10, 2), $rows[$affiliate->id]['commission']['amount']);
    }

    public function test_quote_prefers_the_marketer_profile_rule_over_the_category_default(): void
    {
        $affiliate = F::affiliate($this->s);
        $influencer = F::influencer($this->s);
        $countryId = $this->s->vendorListingFbn->country_id;
        MarketerCommissionCountrySetting::create([
            'category_id' => $this->s->category->id, 'country_id' => $countryId, 'currency' => 'AED',
            'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => 5,
        ]);
        foreach ([$affiliate, $influencer] as $marketer) {
            MarketerCommissionRule::create([
                'marketer_id' => $marketer->id, 'scope' => 'products', 'category_type' => Category::class,
                'category_id' => $this->s->category->id, 'commission_mode' => 'fixed', 'commission_flat_amount' => 12,
            ]);
        }

        $quote = app(MarketerCampaignQuoteService::class)->quote($this->s->vendorListingFbn->fresh(), [$affiliate->id, $influencer->id]);
        $rows = collect($quote['rows'])->keyBy('id');

        $this->assertEquals(12, $rows[$affiliate->id]['commission']['amount']);
        $this->assertSame('marketer_rule', $rows[$affiliate->id]['commission']['source']);
        // The influencer's displayed earning is the fee share; the profile rule is exposed as the per-sale commission.
        $this->assertSame('fee_share', $rows[$influencer->id]['commission']['source']);
        $this->assertEquals(12, $rows[$influencer->id]['per_sale_commission']['amount']);
    }
}
