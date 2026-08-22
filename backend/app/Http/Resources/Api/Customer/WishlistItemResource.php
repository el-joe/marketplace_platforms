<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function __construct($resource, private readonly Country $country)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $isAdmin = !is_null($item->admin_listing_id);
        $listing = $isAdmin ? $item->adminListing : $item->vendorListing;

        return [
            'id' => $item->id,
            'added_at' => $item->added_at,
            'listing_type' => $isAdmin ? 'admin_listing' : 'vendor_listing',
            'listing' => $listing
                ? ($isAdmin
                    ? (new AdminListingResource($listing, $this->country))->toArray($request)
                    : (new VendorListingResource($listing, $this->country))->toArray($request))
                : null,
        ];
    }
}
