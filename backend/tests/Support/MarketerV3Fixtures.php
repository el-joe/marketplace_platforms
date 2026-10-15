<?php

namespace Tests\Support;

use App\Enums\CommissionVertical;
use App\Enums\TravelAgencyStatus;
use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Models\Admin;
use App\Models\City;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerDefaultCommission;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Support\Str;
use Tests\Support\CampaignSampleFixtures as F;

/**
 * v3 (marketer types / commissions refactor) builders shared by the R10 tests. Build on MarketplaceScenario and
 * CampaignSampleFixtures; nothing here is specific to one test class.
 */
class MarketerV3Fixtures
{
    /** Platform default for a country + vertical (Marketer Settings page). */
    public static function defaults(Country|string $country, string $vertical = 'product', float $influencerFixed = 0, float $feePct = 0, float $feeFixed = 0, ?string $categoryId = null): MarketerDefaultCommission
    {
        return MarketerDefaultCommission::create([
            'country_id' => $country instanceof Country ? $country->id : $country,
            'category_type' => $vertical,
            'category_id' => $categoryId,
            'influencer_fixed_amount' => $influencerFixed,
            'commission_fee_percentage' => $feePct,
            'commission_fee_fixed' => $feeFixed,
        ]);
    }

    /** A marketer's profile rule on the vertical-wide row (category_id NULL) of the scope. */
    public static function rule(Marketer $marketer, string $vertical = 'product', ?float $rate = null, ?float $flat = null, ?float $feePct = null, ?float $feeFixed = null, ?string $categoryId = null): MarketerCommissionRule
    {
        $mode = $rate && $flat ? 'both' : ($flat ? 'fixed' : 'percentage');

        return MarketerCommissionRule::create([
            'marketer_id' => $marketer->id,
            'scope' => MarketerCommissionRule::scopeFor(CommissionVertical::from($vertical)),
            'marketer_type' => null,
            'category_id' => $categoryId,
            'commission_mode' => $mode,
            'commission_rate' => $rate ?? 0,
            'commission_flat_amount' => $flat,
            'fee_percentage' => $feePct,
            'fee_fixed' => $feeFixed,
        ]);
    }

    public static function broker(MarketplaceScenario $s): Marketer
    {
        return F::marketer($s, 'broker');
    }

    /**
     * A vendor campaign on the FBN listing, approved by an admin, every invitee accepted.
     * Influencers (influencer-only marketers) are reserved from the budget automatically.
     *
     * @param  array<int, Marketer>  $marketers
     */
    public static function productCampaign(MarketplaceScenario $s, array $marketers, float $budget = 1000, bool $open = false): MarketerCampaign
    {
        $service = app(MarketerCampaignService::class);
        $campaign = $service->createCampaign(
            CampaignOwner::vendor($s->vendor),
            CampaignSource::vendorListing($s->vendorListingFbn->id),
            [
                'country_id' => $s->country->id,
                'currency' => 'AED',
                'max_commission_budget' => $budget,
                'marketer_ids' => array_map(fn (Marketer $m) => $m->id, $marketers),
                'open_to_affiliates' => $open,
            ]
        );

        return self::goLive($campaign, $marketers);
    }

    /** @param  array<int, Marketer>  $marketers */
    public static function goLive(MarketerCampaign $campaign, array $marketers): MarketerCampaign
    {
        $service = app(MarketerCampaignService::class);
        $service->approveCampaign($campaign, Admin::factory()->create());
        foreach ($marketers as $marketer) {
            $service->acceptInvitation($campaign->invitations()->where('marketer_id', $marketer->id)->sole());
        }

        return $campaign->fresh();
    }

    public static function invitationOf(MarketerCampaign $campaign, Marketer $marketer): MarketerCampaignInvitation
    {
        return $campaign->invitations()->where('marketer_id', $marketer->id)->sole()->fresh();
    }

    // ── Classified ──────────────────────────────────────────────────────────

    public static function classifiedVendor(MarketplaceScenario $s): Vendor
    {
        $s->vendor->forceFill(['vendor_type' => 'classified_vendor'])->save();

        return $s->vendor->fresh();
    }

    public static function classifiedListing(MarketplaceScenario $s, array $overrides = []): ClassifiedListing
    {
        $city = City::create(['country_id' => $s->country->id, 'name_en' => 'City '.Str::random(4), 'name_ar' => 'مدينة', 'is_active' => true]);
        $category = ClassifiedCategory::create([
            'name_en' => 'Cars', 'name_ar' => 'سيارات', 'slug' => 'cars-'.Str::lower(Str::random(6)), 'is_active' => true, 'sort_order' => 1,
        ]);

        return ClassifiedListing::create(array_merge([
            'listing_number' => 'CL-'.Str::upper(Str::random(8)),
            'seller_type' => Vendor::class,
            'seller_id' => $s->vendor->id,
            'classified_category_id' => $category->id,
            'country_id' => $s->country->id,
            'city_id' => $city->id,
            'listing_purpose' => 'sale',
            'title_en' => 'Used car',
            'title_ar' => 'سيارة مستعملة',
            'description_en' => 'Good',
            'description_ar' => 'جيدة',
            'price' => 100000,
            'currency' => 'AED',
            'price_negotiable' => true,
            'status' => 'active',
        ], $overrides));
    }

    /** @param  array<int, Marketer>  $marketers */
    public static function classifiedCampaign(MarketplaceScenario $s, ClassifiedListing $listing, array $marketers, float $budget = 1000, bool $open = false): MarketerCampaign
    {
        $campaign = app(MarketerCampaignService::class)->createCampaign(
            CampaignOwner::vendor($s->vendor),
            CampaignSource::classifiedListing($listing->id),
            [
                'max_commission_budget' => $budget,
                'marketer_ids' => array_map(fn (Marketer $m) => $m->id, $marketers),
                'open_to_affiliates' => $open,
            ]
        );

        return self::goLive($campaign, $marketers);
    }

    // ── Travel ──────────────────────────────────────────────────────────────

    public static function agency(MarketplaceScenario $s): TravelAgency
    {
        return TravelAgency::create([
            'name' => 'Agency '.Str::random(4),
            'email' => 'agency-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => bcrypt('password'),
            'license_number' => 'TL-'.Str::upper(Str::random(8)),
            'country_id' => $s->country->id,
            'status' => TravelAgencyStatus::Active,
            'approved_at' => now(),
        ]);
    }

    public static function agencyMember(TravelAgency $agency, bool $owner = true): TravelAgencyMember
    {
        return TravelAgencyMember::create([
            'travel_agency_id' => $agency->id,
            'name' => $owner ? 'Owner' : 'Staff',
            'email' => ($owner ? 'owner-' : 'staff-').Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => bcrypt('password'),
            'role' => $owner ? 'owner' : 'staff',
            'is_owner' => $owner,
            'is_active' => true,
        ]);
    }

    public static function package(TravelAgency $agency, array $overrides = []): TravelPackage
    {
        return TravelPackage::forceCreate(array_merge([
            'travel_agency_id' => $agency->id, 'title_en' => 'Trip', 'title_ar' => 'رحلة', 'price' => 1000,
            'currency' => 'AED', 'duration_days' => 5, 'duration_nights' => 4, 'available_seats' => 50, 'seats_booked' => 0,
            'status' => TravelPackageStatus::Active, 'departure_date' => now()->addMonth(), 'return_date' => now()->addMonths(2),
        ], $overrides));
    }

    public static function customer(): Customer
    {
        $tag = Str::lower(Str::random(8));

        return Customer::forceCreate(['name' => 'C', 'email' => $tag.'@x.test', 'phone' => $tag, 'password' => 'x']);
    }

    /** A booking made through a marketer's link (the saved() hook syncs the commission when the status changes). */
    public static function booking(TravelPackage $package, ?MarketerCampaign $campaign = null, ?Marketer $marketer = null, array $overrides = []): TravelBooking
    {
        $referral = $campaign && $marketer
            ? $campaign->invitations()->where('marketer_id', $marketer->id)->value('referral_code')
            : null;

        return TravelBooking::forceCreate(array_merge([
            'travel_package_id' => $package->id,
            'customer_id' => self::customer()->id,
            'travelers_count' => 1,
            'total_price' => (float) $package->price,
            'status' => TravelBookingStatus::PendingDocuments,
            'marketer_campaign_id' => $campaign?->id,
            'marketer_id' => $marketer?->id,
            'referral_code' => $referral,
        ], $overrides));
    }

    /** @param  array<int, Marketer>  $marketers */
    public static function travelCampaign(MarketplaceScenario $s, TravelAgency $agency, TravelPackage $package, array $marketers, float $budget = 1000, bool $open = false): MarketerCampaign
    {
        $campaign = app(MarketerCampaignService::class)->createCampaign(
            CampaignOwner::travelAgency($agency),
            CampaignSource::travelPackage($package->id),
            [
                'max_commission_budget' => $budget,
                'marketer_ids' => array_map(fn (Marketer $m) => $m->id, $marketers),
                'open_to_affiliates' => $open,
            ]
        );

        return self::goLive($campaign, $marketers);
    }
}
