<?php

namespace App\Http\Resources\Customer;

use App\Enums\GlobalSystemType;
use App\Services\Customer\ListingIdentifierService;
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
        $product = $listing->productVariant->product;

        return [
            'listing_id' => $listing->id,
            'listing_ref' => app(ListingIdentifierService::class)->buildListingRef($listing),
            'sku' => $listing->productVariant->sku,
            'name_en' => $product->name_en,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->unit_price * $this->quantity,
            'thumbnail' => $product->images->firstWhere('is_primary', true)?->url ?? $product->images->first()?->url,
            'vendor_name' => $isAdminListing ? 'noon' : $listing->vendor?->store_name,
            'is_admin_listing' => $isAdminListing,
        ];
    }
}
