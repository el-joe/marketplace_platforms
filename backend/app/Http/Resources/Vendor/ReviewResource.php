<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'vendor_listing_id'    => $this->vendor_listing_id,
            'rating'               => $this->rating,
            'title'                => $this->title,
            'body'                 => $this->body,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'status'               => $this->status->value,
            'helpful_count'        => $this->helpful_count,
            'not_helpful_count'    => $this->not_helpful_count,
            'created_at'           => $this->created_at?->toISOString(),
            // customer_id intentionally omitted — vendor sees aggregated feedback, not PII.
        ];
    }
}
