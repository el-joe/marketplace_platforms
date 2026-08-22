<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the pre-shaped listing-card array built by
 * ListingQueryService::toCardShape() / SponsoredProductService::inject().
 */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
