<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the fully-assembled listing detail payload built by
 * ListingDetailController (listing/seller/delivery/product/variant/reviews/etc.
 * shapes). The sub-shapes are already pure arrays built from several
 * independent enrichment services, so they are passed through as-is here
 * rather than re-derived field-by-field.
 */
class ListingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
