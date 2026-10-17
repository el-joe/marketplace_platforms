<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\Currency;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketingFeeCountrySetting;
use App\Models\VendorAdmin;
use App\Services\Marketer\CampaignSaleRecorder;
use App\Services\Marketer\ConversionSource;
use App\Services\MarketerCampaignQuoteService;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Max Commission Budget is optional (blank / 0 = unlimited, which needs an end date); a given budget must be STRICTLY
 * greater than the influencers' fixed total. Campaign fee = 10% (country setting) of the base: the budget, or for an
 * unlimited campaign the influencers' fixed total.
 */
class OptionalCampaignBudgetTest extends TestCase
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
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 0);
        MarketingFeeCountrySetting::create(['country_id' => $this->s->country->id, 'fixed_amount' => 0, 'fixed_percentage' => 10]);
        Admin::factory()->create();
        Currency::firstOrCreate(['code' => 'AED'], ['name' => 'Dirham', 'symbol' => 'AED']);

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
    }

    private function store(array $payload)
    {
        return $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), $payload + [
            'vendor_listing_id' => $this->s->vendorListingFbn->id,
        ]);
    }

    private function campaigns()
    {
        return MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id);
    }

    private function tomorrow(int $days = 5): string
    {
        return now()->addDays($days)->toDateString();
    }

    /** @return array<int, Marketer> */
    private function influencersWithFixed(array $amounts): array
    {
        $list = [];
        foreach ($amounts as $amount) {
            $list[] = $m = F::influencer($this->s);
            V::rule($m, 'product', flat: $amount);
        }

        return $list;
    }

    public function test_the_owners_scenario_three_influencers_7900_with_budget_5000_is_rejected_by_the_server(): void
    {
        [$a, $b, $c] = $this->influencersWithFixed([2500, 2600, 2800]);

        $this->store(['marketer_ids' => [$a->id, $b->id, $c->id], 'max_commission_budget' => 5000])
            ->assertSessionHasErrors('max_commission_budget');

        $this->assertSame(0, $this->campaigns()->count());
        $this->assertFalse((bool) $this->s->vendorListingFbn->fresh()->campaign_enabled);
    }

    public function test_the_same_scenario_is_rejected_for_influencers_sent_alongside_open_to_affiliates(): void
    {
        [$a, $b, $c] = $this->influencersWithFixed([2500, 2600, 2800]);
        F::affiliate($this->s);

        $this->store(['marketer_ids' => [$a->id, $b->id, $c->id], 'max_commission_budget' => 5000, 'open_to_affiliates' => 1])
            ->assertSessionHasErrors('max_commission_budget');

        $this->assertSame(0, $this->campaigns()->count());
    }

    public function test_a_budget_equal_to_or_below_the_influencers_total_is_rejected_and_above_it_is_accepted(): void
    {
        [$a, $b] = $this->influencersWithFixed([200, 200]);
        $ids = [$a->id, $b->id];

        $this->store(['marketer_ids' => $ids, 'max_commission_budget' => 400])->assertSessionHasErrors('max_commission_budget');
        $this->store(['marketer_ids' => $ids, 'max_commission_budget' => 399.99])->assertSessionHasErrors('max_commission_budget');
        $this->store(['marketer_ids' => $ids, 'max_commission_budget' => 100])->assertSessionHasErrors('max_commission_budget');
        $this->assertSame(0, $this->campaigns()->count());

        $this->store(['marketer_ids' => $ids, 'max_commission_budget' => 400.01])->assertSessionHasNoErrors()->assertRedirect();
        $campaign = $this->campaigns()->sole();
        $this->assertEquals(400, $campaign->influencer_fixed_reserved);
        $this->assertEquals(0.01, $campaign->budgetRemaining());
    }

    public function test_totals_are_additive_for_a_limited_budget(): void
    {
        [$a] = $this->influencersWithFixed([200]);

        $this->store(['marketer_ids' => [$a->id], 'max_commission_budget' => 1000])->assertSessionHasNoErrors();

        $campaign = $this->campaigns()->sole();
        $this->assertEquals(1000, $campaign->campaign_subtotal);
        $this->assertEquals(100, $campaign->marketing_fee_total);
        $this->assertEquals(1100, $campaign->campaign_grand_total);
        $this->assertEquals(800, $campaign->budgetRemaining());
    }

    public function test_a_blank_or_zero_budget_with_an_end_date_is_stored_as_zero_and_priced_on_the_influencers_total(): void
    {
        [$a, $b] = $this->influencersWithFixed([200, 300]);

        foreach (['', '0', null] as $blank) {
            $this->campaigns()->forceDelete();
            $this->store(['marketer_ids' => [$a->id, $b->id], 'max_commission_budget' => $blank, 'ends_at' => $this->tomorrow()])
                ->assertSessionHasNoErrors()->assertRedirect();

            $campaign = $this->campaigns()->sole();
            $this->assertEquals(0, $campaign->max_commission_budget);
            $this->assertNull($campaign->budgetRemaining());
            $this->assertTrue($campaign->isUnlimitedBudget());
            $this->assertEquals(500, $campaign->influencer_fixed_reserved);
            // Fee snapshot recomputed AFTER the reserve: base = reserved total.
            $this->assertEquals(500, $campaign->campaign_subtotal);
            $this->assertEquals(50, $campaign->marketing_fee_total);
            $this->assertEquals(550, $campaign->campaign_grand_total);
            $this->assertNotNull($campaign->ends_at);
            $this->assertStringContainsString($campaign->ends_at->format('Y/m/d'), $campaign->budgetLabel());
        }
    }

    public function test_the_budget_field_may_be_omitted_entirely(): void
    {
        $affiliate = F::affiliate($this->s);

        $this->store(['open_to_affiliates' => 1, 'ends_at' => $this->tomorrow()])->assertSessionHasNoErrors()->assertRedirect();

        $campaign = $this->campaigns()->sole();
        $this->assertEquals(0, $campaign->max_commission_budget);
        $this->assertEquals(0, $campaign->marketing_fee_total);
        $this->assertSame([$affiliate->id], $campaign->requested_marketer_vendor_ids);
    }

    public function test_an_unlimited_campaign_without_an_end_date_is_rejected(): void
    {
        [$a] = $this->influencersWithFixed([200]);

        $this->store(['marketer_ids' => [$a->id], 'max_commission_budget' => ''])->assertSessionHasErrors('ends_at');
        $this->store(['marketer_ids' => [$a->id], 'max_commission_budget' => 0])->assertSessionHasErrors('ends_at');
        $this->store(['marketer_ids' => [$a->id], 'ends_at' => now()->toDateString()])->assertSessionHasErrors('ends_at');
        $this->assertSame(0, $this->campaigns()->count());
    }

    public function test_with_a_limited_budget_the_end_date_stays_optional(): void
    {
        [$a] = $this->influencersWithFixed([200]);

        $this->store(['marketer_ids' => [$a->id], 'max_commission_budget' => 500])->assertSessionHasNoErrors();
        $this->assertNull($this->campaigns()->sole()->ends_at);
    }

    public function test_the_service_guards_an_unlimited_owner_funded_campaign_without_an_end_date(): void
    {
        $affiliate = F::affiliate($this->s);

        $this->expectException(ValidationException::class);
        app(MarketerCampaignService::class)->createCampaign(
            CampaignOwner::vendor($this->s->vendor),
            CampaignSource::vendorListing($this->s->vendorListingFbn->id),
            ['country_id' => $this->s->country->id, 'currency' => 'AED', 'max_commission_budget' => 0, 'marketer_ids' => [$affiliate->id]]
        );
    }

    public function test_the_service_refuses_a_budget_equal_to_the_influencers_total_on_creation(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200);
        $a = F::influencer($this->s);

        try {
            app(MarketerCampaignService::class)->createCampaign(
                CampaignOwner::vendor($this->s->vendor),
                CampaignSource::vendorListing($this->s->vendorListingFbn->id),
                ['country_id' => $this->s->country->id, 'currency' => 'AED', 'max_commission_budget' => 200, 'marketer_ids' => [$a->id]]
            );
            $this->fail('A budget equal to the influencers\' total must be refused.');
        } catch (\RuntimeException) {
            $this->assertSame(0, $this->campaigns()->count());
        }
    }

    public function test_quote_flags_for_unlimited_limited_and_too_small_budgets(): void
    {
        [$a, $b] = $this->influencersWithFixed([200, 300]);
        $quotes = app(MarketerCampaignQuoteService::class);
        $listing = $this->s->vendorListingFbn->fresh();

        $unlimited = $quotes->quote($listing, [$a->id, $b->id], 0);
        $this->assertTrue($unlimited['unlimited']);
        $this->assertTrue($unlimited['requires_end_date']);
        $this->assertTrue($unlimited['fits_budget']);
        $this->assertNull($unlimited['remaining_budget']);
        $this->assertEquals(0, $unlimited['shortfall']);
        $this->assertEquals(500, $unlimited['campaign_subtotal']);
        $this->assertEquals(50, $unlimited['marketing']['total']);
        $this->assertEquals(550, $unlimited['campaign_grand_total']);

        $limited = $quotes->quote($listing, [$a->id, $b->id], 1000);
        $this->assertFalse($limited['unlimited']);
        $this->assertFalse($limited['requires_end_date']);
        $this->assertTrue($limited['fits_budget']);
        $this->assertEquals(500, $limited['remaining_budget']);
        $this->assertEquals(1000, $limited['campaign_subtotal']);
        $this->assertEquals(1100, $limited['campaign_grand_total']);

        $equal = $quotes->quote($listing, [$a->id, $b->id], 500);
        $this->assertFalse($equal['fits_budget']);
        $this->assertEquals(0.01, $equal['shortfall']);

        $small = $quotes->quote($listing, [$a->id, $b->id], 450);
        $this->assertFalse($small['fits_budget']);
        $this->assertEquals(50.01, $small['shortfall']);
    }

    public function test_the_quote_endpoint_returns_the_flags_and_the_wizard_renders_the_unlimited_ui(): void
    {
        [$a] = $this->influencersWithFixed([200]);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->getJson(route('partner.marketer-campaigns.quote', ['listing_id' => $this->s->vendorListingFbn->id, 'marketer_ids' => [$a->id], 'budget' => 0]))
            ->assertOk()
            ->assertJsonPath('unlimited', true)
            ->assertJsonPath('requires_end_date', true)
            ->assertJsonPath('fits_budget', true)
            ->assertJsonPath('remaining_budget', null)
            ->assertJsonPath('campaign_grand_total', 220);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee(__('marketer_campaigns.wizard.budget_label_hint'), false)
            ->assertSee('data-testid="unlimited-affiliates-warning"', false)
            ->assertSee('data-testid="budget-must-exceed"', false)
            ->assertSee('guardSubmit', false);
    }

    public function test_one_influencer_of_2700_with_an_empty_budget_is_unlimited_not_a_negative_remaining(): void
    {
        [$a] = $this->influencersWithFixed([2700]);

        $quote = app(MarketerCampaignQuoteService::class)->quote($this->s->vendorListingFbn->fresh(), [$a->id], 0);
        $this->assertTrue($quote['fits_budget']);
        $this->assertTrue($quote['unlimited']);
        $this->assertNull($quote['remaining_budget']);
        $this->assertEquals(2700, $quote['campaign_subtotal']);
        $this->assertEquals(2970, $quote['campaign_grand_total']);

        $html = $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))->assertOk()->getContent();
        $this->assertStringNotContainsString('quote.fits_budget', $html);
        $this->assertStringContainsString('data-testid="end-date-required"', $html);
    }

    public function test_an_unlimited_campaign_pays_uncapped_affiliate_commissions_and_completes_at_its_end_date(): void
    {
        V::defaults($this->s->country, 'travel', influencerFixed: 200);
        $agency = V::agency($this->s);
        $package = V::package($agency);
        $influencer = F::influencer($this->s);
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'travel', rate: 10);

        $campaign = app(MarketerCampaignService::class)->createCampaign(
            CampaignOwner::travelAgency($agency),
            CampaignSource::travelPackage($package->id),
            [
                'max_commission_budget' => 0, 'marketer_ids' => [$influencer->id, $affiliate->id], 'influencer_ids' => [$influencer->id],
                'ends_at' => now()->addDays(3)->endOfDay(),
            ]
        );
        $campaign = V::goLive($campaign, [$influencer, $affiliate]);
        $this->assertSame('active', $campaign->status);
        $this->assertEquals(220, $campaign->campaign_grand_total);

        // Far more commission than any budget could have held: nothing caps it and the campaign stays live.
        $recorder = app(CampaignSaleRecorder::class);
        $invitation = V::invitationOf($campaign, $affiliate);
        $total = 0;
        foreach (range(1, 5) as $i) {
            $conversion = $recorder->record($invitation, ConversionSource::travelBooking(V::booking($package)->id), 100000);
            $total += (float) $conversion->gross_commission_amount;
        }
        $this->assertEquals(50000, $total);
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertFalse($campaign->fresh()->isBudgetExhausted());

        $this->travel(4)->days();
        $this->assertSame(1, app(MarketerCampaignService::class)->completeEndedCampaigns());

        $campaign = $campaign->fresh();
        $this->assertSame('done', $campaign->status);
        $this->assertNotNull($campaign->influencers_settled_at);
        $this->assertSame('paid', V::invitationOf($campaign, $influencer)->influencer_fixed_status);
    }

    public function test_partner_edit_cannot_drop_the_budget_to_the_influencers_total_and_unlimited_needs_an_end_date(): void
    {
        [$a, $b] = $this->influencersWithFixed([200, 300]);
        $this->store(['marketer_ids' => [$a->id, $b->id], 'max_commission_budget' => 1000])->assertSessionHasNoErrors();
        $campaign = $this->campaigns()->sole();
        $url = route('partner.marketer-campaigns.update', $campaign);
        $this->actingAs($this->vendorAdmin, 'vendor');

        $this->put($url, ['max_commission_budget' => 500])->assertSessionHasErrors('max_commission_budget');
        $this->put($url, ['max_commission_budget' => 400])->assertSessionHasErrors('max_commission_budget');
        $this->put($url, ['max_commission_budget' => ''])->assertSessionHasErrors('ends_at');
        $this->assertEquals(1000, $campaign->fresh()->max_commission_budget);

        $this->put($url, ['max_commission_budget' => 600])->assertSessionHasNoErrors();
        $this->assertEquals(600, $campaign->fresh()->max_commission_budget);
        $this->assertEquals(660, $campaign->fresh()->campaign_grand_total);

        $this->put($url, ['max_commission_budget' => '', 'ends_at' => $this->tomorrow()])->assertSessionHasNoErrors();
        $fresh = $campaign->fresh();
        $this->assertEquals(0, $fresh->max_commission_budget);
        $this->assertEquals(500, $fresh->campaign_subtotal);
        $this->assertEquals(550, $fresh->campaign_grand_total);
    }

    public function test_admin_create_accepts_unlimited_with_an_end_date_and_rejects_it_without(): void
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketer_campaigns.create', 'guard_name' => 'admin']);
        $admin = Admin::first();
        Permission::firstOrCreate(['name' => 'marketer_campaigns.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['marketer_campaigns.create', 'marketer_campaigns.view']);
        $affiliate = F::affiliate($this->s);

        $payload = [
            'vendor_id' => $this->s->vendor->id, 'vendor_listing_id' => $this->s->vendorListingFbn->id,
            'marketer_ids' => [$affiliate->id], 'country_id' => $this->s->country->id, 'currency' => 'AED',
            'max_commission_budget' => '', 'open_to_affiliates' => 1,
        ];

        $this->actingAs($admin, 'admin')->post(route('admin.marketer-campaigns.store'), $payload)->assertSessionHasErrors('ends_at');
        $this->assertSame(0, $this->campaigns()->count());

        $this->actingAs($admin, 'admin')->post(route('admin.marketer-campaigns.store'), $payload + ['ends_at' => $this->tomorrow()])
            ->assertSessionHasNoErrors();
        $campaign = $this->campaigns()->sole();
        $this->assertEquals(0, $campaign->max_commission_budget);
        $this->assertNotNull($campaign->ends_at);
    }

    public function test_admin_create_rejects_a_budget_that_does_not_exceed_the_influencers_total(): void
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketer_campaigns.create', 'guard_name' => 'admin']);
        $admin = Admin::first();
        Permission::firstOrCreate(['name' => 'marketer_campaigns.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['marketer_campaigns.create', 'marketer_campaigns.view']);
        [$a, $b] = $this->influencersWithFixed([200, 300]);

        $this->actingAs($admin, 'admin')->post(route('admin.marketer-campaigns.store'), [
            'vendor_id' => $this->s->vendor->id, 'vendor_listing_id' => $this->s->vendorListingFbn->id,
            'marketer_ids' => [$a->id, $b->id], 'country_id' => $this->s->country->id, 'currency' => 'AED',
            'max_commission_budget' => 500,
        ])->assertSessionHas('error');

        $this->assertSame(0, $this->campaigns()->count());
    }
}
