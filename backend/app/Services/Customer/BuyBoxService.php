<?php

namespace App\Services\Customer;

use App\Enums\VendorListingStatus;
use App\Models\Country;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class BuyBoxService
{
    /**
     * Return active vendor listings for the product's variants in the given country,
     * buy-box winner (highest score) listed first.
     *
     * buy_box_winners table is not yet live; winner is derived from vendor_listings.score
     * which is populated by the platform's scoring job.
     */
    public function getListings(Product $product, Country $country): Collection
    {
        $variantIds = $product->variants()
            ->where('is_active', true)
            ->pluck('id');

        return \App\Models\VendorListing::query()
            ->with([
                'vendor:id,store_name,store_slug,store_rating_avg,store_rating_count,created_at,warranty_months,easy_returns_enabled,secure_payments_enabled',
                'warehouseInventories',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ])
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('warehouseInventories', function ($q) {
                $q->where('quantity_available', '>', 0);
            })
            ->orderByRaw('score IS NULL, score DESC')
            ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
            ->orderByDesc('rating_count')
            ->orderBy('price')
            ->get();
    }
}
