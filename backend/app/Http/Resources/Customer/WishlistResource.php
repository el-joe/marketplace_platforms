<?php

namespace App\Http\Resources\Customer;

use App\Enums\GlobalSystemType;
use App\Services\Customer\ListingIdentifierService;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->vendorListing;
        $variant = $listing?->productVariant;
        $product = $variant?->product;

        return [
            'id'              => $this->id,
            'added_at'        => $this->added_at,
            'listing_id'      => $listing?->id,
            'listing_ref'     => $listing ? app(ListingIdentifierService::class)->buildListingRef($listing) : null,
            'sku'             => $variant?->sku,
            'price'     => $listing?->price,
            'price_formatted' => $listing ? number_format($listing->price / 100, 2) : null,
            'currency'        => $listing?->currency,
            'status'          => $listing?->status?->value,
            'is_admin_listing' => $listing?->global_system_type === GlobalSystemType::ExpressFbn,
            'product'         => $product ? [
                'id'        => $product->id,
                'name'      => Bilingual::pair($product, 'name'),
                'slug'      => $product->slug,
                'thumbnail' => $product->images?->first()?->url ?? null,
            ] : null,
            'vendor'          => $listing?->vendor ? [
                'id'         => $listing->vendor->id,
                'store_name' => $listing->vendor->store_name,
            ] : null,
            'shipping_badge'  => $listing?->primaryShippingMethod ? [
                'label'             => Bilingual::pairFromKeys($listing->primaryShippingMethod, 'badge_label_ar', 'badge_label_en'),
                'color_hex'         => $listing->primaryShippingMethod->badge_color_hex,
                'text_color_hex'    => $listing->primaryShippingMethod->badge_text_color_hex,
                'delivery_days_min' => $listing->primaryShippingMethod->min_delivery_days,
                'delivery_days_max' => $listing->primaryShippingMethod->max_delivery_days,
            ] : null,
        ];
    }
}
