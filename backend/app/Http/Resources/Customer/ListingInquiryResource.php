<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingInquiryResource extends JsonResource
{
    public string $listingSlug = '';

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_slug' => $this->listingSlug,
            'status' => $this->status?->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
