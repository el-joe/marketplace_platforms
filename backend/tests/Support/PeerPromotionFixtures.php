<?php

namespace Tests\Support;

use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerListing;
use App\Models\WarehouseInventory;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignSource;
use Illuminate\Support\Str;

/** Shared builders for the P13 influencer-to-influencer promotion tests. */
class PeerPromotionFixtures
{
    /** An influencer's own FBN listing (source_listing_id NULL) with stock in a platform warehouse. */
    public static function ownListing(MarketplaceScenario $s, Marketer $owner, int $stock = 20, string $status = 'active', int $price = 20000): MarketerListing
    {
        $listing = MarketerListing::create([
            'marketer_id' => $owner->id,
            'product_variant_id' => $s->variants[1]->id,
            'listing_category' => 'product',
            'country_id' => $s->country->id,
            'warehouse_id' => $s->platformWarehouse->id,
            'fulfillment_model' => 'fbn',
            'price' => $price,
            'currency' => 'AED',
            'status' => $status,
            'condition' => 'new',
            'referral_code' => 'OWN-'.Str::upper(Str::random(8)),
        ]);

        WarehouseInventory::create([
            'marketer_listing_id' => $listing->id,
            'warehouse_id' => $s->platformWarehouse->id,
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
        ]);

        return $listing->fresh();
    }

    /** Admin-defined per-unit influencer commission for the scenario category + country. */
    public static function commission(MarketplaceScenario $s, int $influencerAmount = 3000): void
    {
        MarketerCommissionCountrySetting::updateOrCreate(
            ['category_id' => $s->category->id, 'country_id' => $s->country->id],
            ['influencer_commission_amount' => $influencerAmount, 'affiliate_commission_amount' => 1000, 'currency' => 'AED']
        );
    }

    public static function request(Marketer $promoter, MarketerListing $listing): MarketerCampaign
    {
        return app(MarketerCampaignService::class)->requestCampaign($promoter, CampaignSource::marketerListing($listing->id), []);
    }
}
