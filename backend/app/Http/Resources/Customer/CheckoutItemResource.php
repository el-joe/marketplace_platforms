<?php

namespace App\Http\Resources\Customer;

use App\Enums\GlobalSystemType;
use App\Services\Customer\ListingIdentifierService;
use App\Services\Media\ListingImageResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cart item as shown in the checkout preview's `items` list.
 * Wraps a CartItem model (with vendorListing.productVariant.product loaded).
 */
class CheckoutItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->vendorListing;
        $isAdminListing = $listing->global_system_type === GlobalSystemType::ExpressFbn;
        $variant = $listing->productVariant;
        $product = $variant->product;

        $images = app(ListingImageResolver::class)->gallery($variant->id);
        $thumbnail = $images[0]->url ?? null;

        return [
            'listing_id' => $listing->id,
            'listing_ref' => app(ListingIdentifierService::class)->buildListingRef($listing),
            'sku' => $variant->sku,
            'name' => $variant->displayNamePair(),
            'name_en' => $variant->displayName('en'),
            'name_ar' => $variant->displayName('ar'),
            'product_name' => ['ar' => $product->name_ar, 'en' => $product->name_en],
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->unit_price * $this->quantity,
            'thumbnail' => $thumbnail,
            'image' => $thumbnail ? ['url' => $thumbnail, 'alt' => $images[0]->alt] : null,
            'images' => array_map(fn ($i) => $i->toArray(), $images),
            'vendor_name' => $isAdminListing ? 'noon' : $listing->vendor?->store_name,
            'is_admin_listing' => $isAdminListing,
        ];
    }
}
