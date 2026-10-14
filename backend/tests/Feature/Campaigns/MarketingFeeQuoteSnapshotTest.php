<?php

namespace Tests\Feature\Campaigns;

use App\Enums\FulfillmentModel;
use App\Enums\MarketingFeeType;
use App\Models\Admin;
use App\Models\MarketerCampaign;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\VendorAdmin;
use App\Services\MarketerCampaignQuoteService;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** P4: quote with marketing fees, vendor create form, campaign snapshot and the influencer-only approval guard. */
class MarketingFeeQuoteSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private VendorAdmin $vendorAdmin;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        app()->setLocale('en');
        $this->s = MarketplaceScenario::make()->build();
        $this->fee(9);

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit', 'marketer_campaigns.delete'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }

        $perms = ['vendors.assigned_only', 'marketer_campaigns.view', 'marketer_campaigns.approve'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(array_slice($perms, 1));
    }

    private function fee(float $amount): void
    {
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => $amount, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
    }

    private function fixedFee(float $amount, float $pct): void
    {
        MarketingFeeCountrySetting::updateOrCreate(['country_id' => $this->s->country->id], ['fixed_amount' => $amount, 'fixed_percentage' => $pct]);
    }

    private function variableFee(MarketingFeeType $type, float $amount, float $pct): void
    {
        MarketingFeeCategorySetting::updateOrCreate(
            ['country_id' => $this->s->country->id, 'category_id' => $this->s->category->id, 'fulfillment_type' => $type->value],
            ['fixed_amount' => $amount, 'percentage' => $pct]
        );
    }

    private function quoteUrl(string $listingId, array $marketers): string
    {
        return route('partner.listings.marketer-fee', ['listing_id' => $listingId, 'marketer_ids' => array_map(fn ($m) => $m->id, $marketers)]);
    }

    public function test_the_22_69_example_goes_through_the_form_endpoint_and_the_store(): void
    {
        $this->fixedFee(1.9, 0);
        $this->variableFee(MarketingFeeType::Fbn, 0, 10);
        $marketers = [F::influencer($this->s), F::influencer($this->s)];
        $listing = $this->s->vendorListingFbn;

        $json = $this->actingAs($this->vendorAdmin, 'vendor')->getJson($this->quoteUrl($listing->id, $marketers))->assertOk()->json();

        $this->assertEqualsWithDelta(18.0, $json['total_fee'], 0.001);
        $this->assertEqualsWithDelta(0.9, $json['total_fee_vat'], 0.001);
        $this->assertEqualsWithDelta(18.9, $json['campaign_subtotal'], 0.001);
        $this->assertEqualsWithDelta(18.9, $json['total_fee_with_vat'], 0.001);
        $this->assertEqualsWithDelta(1.9, $json['marketing']['fixed_fee'], 0.001);
        $this->assertEqualsWithDelta(1.89, $json['marketing']['variable_fee'], 0.001);
        $this->assertEqualsWithDelta(3.79, $json['marketing']['total'], 0.001);
        $this->assertEqualsWithDelta(22.69, $json['campaign_grand_total'], 0.001);
        $this->assertSame('fbn', $json['fulfillment_type']);
        $this->assertSame('FBN (Fulfilled by Nawy)', $json['fulfillment_type_label']);
        $this->assertFalse($json['wallet']['sufficient']);
        $this->assertEqualsWithDelta(22.69, $json['wallet']['shortfall'], 0.001);

        // Client-posted numbers are ignored: the snapshot comes from the server-side quote.
        $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), [
            'vendor_listing_id' => $listing->id,
            'marketer_ids' => array_map(fn ($m) => $m->id, $marketers),
            'campaign_grand_total' => 1,
            'campaign_subtotal' => 1,
            'marketing_fee_total' => 0,
            'marketing_fee_fixed' => 0,
            'marketing_fee_variable' => 0,
            'fulfillment_type' => 'fbp',
        ])->assertSessionHasNoErrors();

        $campaign = MarketerCampaign::where('vendor_listing_id', $listing->id)->sole();
        $this->assertSame(MarketingFeeType::Fbn, $campaign->fulfillment_type);
        $this->assertEqualsWithDelta(18.9, (float) $campaign->campaign_subtotal, 0.001);
        $this->assertEqualsWithDelta(1.9, (float) $campaign->marketing_fee_fixed, 0.001);
        $this->assertEqualsWithDelta(1.89, (float) $campaign->marketing_fee_variable, 0.001);
        $this->assertEqualsWithDelta(3.79, (float) $campaign->marketing_fee_total, 0.001);
        $this->assertEqualsWithDelta(22.69, (float) $campaign->campaign_grand_total, 0.001);
    }

    public function test_snapshot_equals_the_quote_and_survives_later_setting_changes(): void
    {
        $this->fixedFee(1.9, 0);
        $this->variableFee(MarketingFeeType::Fbn, 0, 10);
        $marketers = [F::influencer($this->s), F::influencer($this->s)];
        $campaign = F::campaign($this->s, $marketers);

        $quote = app(MarketerCampaignQuoteService::class)->quote($this->s->vendorListingFbn, array_map(fn ($m) => $m->id, $marketers));

        $this->assertSame($quote['fulfillment_type'], $campaign->fulfillment_type->value);
        $this->assertEqualsWithDelta($quote['campaign_subtotal'], (float) $campaign->campaign_subtotal, 0.0001);
        $this->assertEqualsWithDelta($quote['marketing']['fixed_fee'], (float) $campaign->marketing_fee_fixed, 0.0001);
        $this->assertEqualsWithDelta($quote['marketing']['variable_fee'], (float) $campaign->marketing_fee_variable, 0.0001);
        $this->assertEqualsWithDelta($quote['marketing']['total'], (float) $campaign->marketing_fee_total, 0.0001);
        $this->assertEqualsWithDelta($quote['campaign_grand_total'], (float) $campaign->campaign_grand_total, 0.0001);

        $this->variableFee(MarketingFeeType::Fbn, 0, 50);
        $this->assertEqualsWithDelta(22.69, (float) $campaign->fresh()->campaign_grand_total, 0.001);
    }

    public function test_each_fulfillment_type_uses_its_own_setting(): void
    {
        $this->fee(10);
        $this->variableFee(MarketingFeeType::Fbn, 0, 10);
        $this->variableFee(MarketingFeeType::Fbm, 0, 20);
        $this->variableFee(MarketingFeeType::Fbp, 0, 30);
        $influencer = F::influencer($this->s);
        $svc = app(MarketerCampaignQuoteService::class);

        // Subtotal is 10 + 5% VAT = 10.50.
        $fbn = $svc->quote($this->s->vendorListingFbn, [$influencer->id]);
        $this->assertSame('fbn', $fbn['fulfillment_type']);
        $this->assertEqualsWithDelta(1.05, $fbn['marketing']['total'], 0.001);
        $this->assertEqualsWithDelta(11.55, $fbn['campaign_grand_total'], 0.001);

        // cross_dock is billed as FBM.
        $this->s->vendorListingFbp->update(['fulfillment_model' => FulfillmentModel::CrossDock->value]);
        $fbm = $svc->quote($this->s->vendorListingFbp->fresh(), [$influencer->id]);
        $this->assertSame('fbm', $fbm['fulfillment_type']);
        $this->assertSame('FBM (cross_dock)', $fbm['fulfillment_type_label']);
        $this->assertEqualsWithDelta(2.10, $fbm['marketing']['total'], 0.001);

        // the remaining "fbm" listing value is billed as FBP.
        $this->s->vendorListingFbp->update(['fulfillment_model' => FulfillmentModel::Fbm->value]);
        $fbp = $svc->quote($this->s->vendorListingFbp->fresh(), [$influencer->id]);
        $this->assertSame('fbp', $fbp['fulfillment_type']);
        $this->assertSame('FBP (fbm)', $fbp['fulfillment_type_label']);
        $this->assertEqualsWithDelta(3.15, $fbp['marketing']['total'], 0.001);
        $this->assertEqualsWithDelta(13.65, $fbp['campaign_grand_total'], 0.001);
    }

    public function test_affiliate_only_campaign_has_zero_marketing_fee_at_creation(): void
    {
        $this->fixedFee(1.9, 0);
        $this->variableFee(MarketingFeeType::Fbn, 5, 10);

        $campaign = F::campaign($this->s, [F::affiliate($this->s)]);

        $this->assertSame(MarketingFeeType::Fbn, $campaign->fulfillment_type);
        $this->assertEquals(0, (float) $campaign->campaign_subtotal);
        $this->assertEquals(0, (float) $campaign->marketing_fee_fixed);
        $this->assertEquals(0, (float) $campaign->marketing_fee_variable);
        $this->assertEquals(0, (float) $campaign->marketing_fee_total);
        $this->assertEquals(0, (float) $campaign->campaign_grand_total);
    }

    public function test_influencer_only_campaign_is_approved_with_zero_per_sale_commission(): void
    {
        $campaign = F::campaign($this->s, [F::influencer($this->s)], 0);
        $this->assertEquals(0, (float) $campaign->marketer_commission_amount);

        app(MarketerCampaignService::class)->approveCampaign($campaign, $this->admin);

        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->invitations()->count());
    }

    public function test_campaign_targeting_affiliates_without_a_commission_is_still_refused(): void
    {
        $svc = app(MarketerCampaignService::class);

        foreach ([[F::affiliate($this->s)], [F::influencer($this->s), F::affiliate($this->s)]] as $marketers) {
            $campaign = F::campaign($this->s, $marketers, 0);
            try {
                $svc->approveCampaign($campaign, $this->admin);
                $this->fail('An affiliate campaign without a resolvable commission must not be approved.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('zero marketer commission', $e->getMessage());
            }
            $this->assertSame('pending_admin', $campaign->fresh()->status);
            $campaign->update(['status' => 'cancelled']);
            $campaign->invitations()->delete();
        }
    }

    public function test_influencer_only_campaign_is_auto_approved_with_zero_commission(): void
    {
        $campaign = F::campaign($this->s, [F::influencer($this->s)], 0);

        app(MarketerCampaignService::class)->autoApproveCampaign($campaign->id);

        $this->assertSame('auto_approved', $campaign->fresh()->status);
    }

    public function test_pages_render_the_breakdown_and_the_snapshot_in_both_languages(): void
    {
        $this->fixedFee(1.9, 0);
        $this->variableFee(MarketingFeeType::Fbn, 0, 10);
        $campaign = F::campaign($this->s, [F::influencer($this->s), F::influencer($this->s)]);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $this->actingAs($this->vendorAdmin, 'vendor')
                ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
                ->assertOk()
                ->assertSee('data-testid="campaign-cost-breakdown"', false)
                ->assertSee('data-testid="quote-grand-total"', false)
                ->assertSee('data-testid="affiliate-billing-explanation"', false)
                ->assertSee(__('marketing_fees.campaign.grand_total'))
                ->assertSee(__('marketing_fees.campaign.fixed_fee'))
                ->assertSee(__('marketing_fees.campaign.variable_fee'))
                ->assertSee(__('marketing_fees.campaign.affiliate_marketing_fees'));

            $this->actingAs($this->vendorAdmin, 'vendor')
                ->get(route('partner.marketer-campaigns.show', $campaign))
                ->assertOk()
                ->assertSee('data-testid="campaign-fee-snapshot"', false)
                ->assertSee('22.69')
                ->assertSee('3.79');

            $this->actingAs($this->vendorAdmin, 'vendor')
                ->get(route('partner.marketer-campaigns.index'))
                ->assertOk()
                ->assertSee('data-testid="campaign-grand-total"', false)
                ->assertSee('22.69');

            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.marketer-campaigns.show', $campaign))
                ->assertOk()
                ->assertSee('data-testid="campaign-fee-snapshot"', false)
                ->assertSee('data-testid="requested-grand-total"', false)
                ->assertSee('22.69');

            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.marketer-campaigns.financials', ['date_from' => now()->subDay()->toDateString()]))
                ->assertOk()
                ->assertSee('data-testid="financials-grand-total"', false)
                ->assertSee('22.69');
        }
    }
}
