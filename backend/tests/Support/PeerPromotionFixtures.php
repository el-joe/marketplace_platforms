<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCommissionRule;
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

    /**
     * The promoter's own profile rule ("Commission Rates by Category", fixed per unit) for the scenario category.
     * D11/D12: this is the only per-sale commission an influencer can have; there is no category-tab default.
     */
    public static function promoterRule(MarketplaceScenario $s, Marketer $promoter, int $perUnit = 3000): MarketerCommissionRule
    {
        return MarketerCommissionRule::create([
            'marketer_id' => $promoter->id,
            'scope' => 'products',
            'category_type' => Category::class,
            'category_id' => $s->category->id,
            'commission_mode' => 'fixed',
            'commission_flat_amount' => $perUnit,
        ]);
    }

    public static function request(Marketer $promoter, MarketerListing $listing): MarketerCampaign
    {
        return app(MarketerCampaignService::class)->requestCampaign($promoter, CampaignSource::marketerListing($listing->id), []);
    }
}
