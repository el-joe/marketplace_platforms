<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'rating'               => $this->rating,
            'title'                => $this->title,
            'body'                 => $this->body,
            'is_verified_purchase' => $this->is_verified_purchase,
            'helpful_count'        => $this->helpful_count,
            'not_helpful_count'    => $this->not_helpful_count,
            'reviewer_name'        => $this->customer?->name ?? 'Anonymous',
            'created_at'           => $this->created_at?->toIso8601String(),
            'images'               => $this->whenLoaded('files', fn() =>
                $this->files->map(fn($f) => $f->full_path)->values()->all()
            ),
            'listing_id'           => $this->vendor_listing_id,
            'seller'               => $this->whenLoaded('vendorListing', fn() =>
                $this->vendorListing?->vendor ? [
                    'id'         => $this->vendorListing->vendor->id,
                    'store_name' => $this->vendorListing->vendor->store_name,
                ] : null
            ),
            'variant'              => $this->whenLoaded('vendorListing', fn() =>
                $this->vendorListing?->productVariant ? [
                    'id'           => $this->vendorListing->productVariant->id,
                    'variant_name' => $this->vendorListing->productVariant->variant_name,
                    'attributes'   => $this->vendorListing->productVariant->variantAttributes->map(fn($va) => [
                        'name'  => $va->attribute ? Bilingual::pair($va->attribute, 'name') : ['ar' => null, 'en' => null],
                        'value' => [
                            'ar' => $va->attributeValue?->value_ar ?? $va->value_text_ar,
                            'en' => $va->attributeValue?->value_en ?? $va->value_text_en,
                        ],
                    ])->values()->all(),
                ] : null
            ),
            'vendor_reply'         => $this->whenLoaded('vendorReply', function () {
                return $this->vendorReply ? [
                    'body'       => $this->vendorReply->body,
                    'created_at' => $this->vendorReply->created_at?->toIso8601String(),
                ] : null;
            }),
        ];
    }
}
