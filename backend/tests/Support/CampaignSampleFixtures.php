<?php

namespace Tests\Support;

use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaign;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\MarketerProfile;
use App\Models\Setting;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Support\Str;

/**
 * Builders shared by the P8 sample tests (service / partner / admin / marketer / payout).
 */
class CampaignSampleFixtures
{
    public static function setting(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'category' => 'marketer', 'is_encrypted' => 0, 'is_public' => 0]);
        Setting::flush();
    }

    /** Category sample quantities (0 = "admin did not set one"). */
    public static function categoryQty(MarketplaceScenario $s, int $platform = 0, int $influencer = 0, int $affiliate = 0, int $minStock = 10): void
    {
        $s->category->update([
            'platform_sample_qty' => $platform,
            'influencer_sample_qty' => $influencer,
            'affiliate_sample_qty' => $affiliate,
            'min_stock_for_campaign' => $minStock,
        ]);
    }

    public static function marketer(MarketplaceScenario $s, string $job): Marketer
    {
        $m = Marketer::create([
            'name' => ucfirst($job).' '.Str::random(4),
            'email' => $job.'-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'marketer_type' => $job,
            'global_status' => 'active',
            'country_id' => $s->country->id,
            'approved_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
        MarketerProfile::create(['marketer_id' => $m->id]);
        $jobRow = MarketerJob::firstOrCreate(['key' => $job], ['name_ar' => $job, 'name_en' => $job, 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $m->id, 'marketer_job_id' => $jobRow->id]);

        return $m;
    }

    public static function influencer(MarketplaceScenario $s): Marketer
    {
        return self::marketer($s, 'influencer');
    }

    public static function affiliate(MarketplaceScenario $s): Marketer
    {
        return self::marketer($s, 'affiliate');
    }

    public static function marketerLogin(Marketer $m): MarketerAdmin
    {
        return MarketerAdmin::create([
            'marketer_id' => $m->id, 'name' => 'Owner', 'email' => 'mo-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    /** Vendor campaign on the FBN listing (40 units in stock) for the given marketers. */
    public static function campaign(MarketplaceScenario $s, array $marketers, int $commission = 5000): MarketerCampaign
    {
        return app(MarketerCampaignService::class)->createCampaign(
            CampaignOwner::vendor($s->vendor),
            CampaignSource::vendorListing($s->vendorListingFbn->id),
            [
                'country_id' => $s->country->id,
                'currency' => 'AED',
                'commission_type' => 'fixed',
                'max_commission_budget' => 100000,
                'marketer_commission_amount' => $commission,
                'marketer_ids' => array_map(fn ($m) => $m->id, $marketers),
            ]
        );
    }
}
