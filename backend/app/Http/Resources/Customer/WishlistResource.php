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
        $images = $variant ? app(\App\Services\Media\ListingImageResolver::class)->gallery($variant->id) : [];
        $thumbnail = $images[0]->url ?? null;

        return [
            'id'              => $this->id,
            'added_at'        => $this->added_at,
            'listing_id'      => $listing?->id,
            'listing_ref'     => $listing ? app(ListingIdentifierService::class)->buildListingRef($listing) : null,
            'sku'             => $variant?->sku,
            'price'     => $listing?->price,
            'price_formatted' => $listing ? number_format($listing->price, 2) : null,
            'currency'        => $listing?->currency,
            'status'          => $listing?->status?->value,
            'is_admin_listing' => $listing?->global_system_type === GlobalSystemType::ExpressFbn,
            'product'         => $product ? [
                'id'        => $product->id,
                'name'      => Bilingual::pair($product, 'name'),
                'slug'      => $product->slug,
                'thumbnail' => $thumbnail,
                'image'     => $thumbnail ? ['url' => $thumbnail, 'alt' => $images[0]->alt] : null,
                'images'    => array_map(fn ($i) => $i->toArray(), $images),
            ] : null,
            'promo_badges'    => $listing ? \App\Services\Customer\PromoBadgeResolver::instance()->lookup('vendor', $listing->id, $product?->id) : [],
            'vendor'          => $listing?->vendor ? [
                'id'         => $listing->vendor->id,
                'store_name' => $listing->vendor->store_name,
            ] : null,
            'shipping_badge'  => $listing?->primaryShippingMethod ? [
                'label'             => Bilingual::pairFromKeys($listing->primaryShippingMethod, 'badge_label_ar', 'badge_label_en'),
                'color_hex'         => $listing->primaryShippingMethod->badge_color_hex,
                'text_color_hex'    => $listing->primaryShippingMethod->badge_text_color_hex,
                'icon_color_hex' => $listing->primaryShippingMethod->badge_icon_color_hex,
                'show_delivery_time' => (bool) $listing->primaryShippingMethod->badge_show_delivery_time,
                'delivery_text' => ['ar' => $listing->primaryShippingMethod->badge_delivery_text_resolved_ar, 'en' => $listing->primaryShippingMethod->badge_delivery_text_resolved_en],
                'icon' => (($listing->primaryShippingMethod->badge_icon ?? 'bolt') === 'none') ? null : ($listing->primaryShippingMethod->badge_icon ?? 'bolt'),
                'badge_image_url' => $listing->primaryShippingMethod->badge_image_url,
                'delivery_days_min' => $listing->primaryShippingMethod->min_delivery_days,
                'delivery_days_max' => $listing->primaryShippingMethod->max_delivery_days,
            ] : null,
        ];
    }
}
