<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CustomerClassifiedListingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'listing_number'          => $this->listing_number,
            'title'                   => Bilingual::pair($this->resource, 'title'),
            'description'             => Bilingual::pair($this->resource, 'description'),
            'listing_purpose'         => $this->listing_purpose,
            'price'             => $this->price,
            'currency'                => $this->currency,
            'price_negotiable'        => $this->price_negotiable,
            'attributes'              => $this->attributes,
            'latitude'                => $this->latitude,
            'longitude'               => $this->longitude,
            'status'                  => $this->status?->value,
            'rejection_reason'        => $this->rejection_reason,
            'views_count'             => $this->views_count,
            'expires_at'              => $this->expires_at?->toDateString(),
            'created_at'              => $this->created_at?->toIso8601String(),
            'country_id'              => $this->country_id,
            'city_id'                 => $this->city_id,
            'category'                => $this->whenLoaded('classifiedCategory', fn () => [
                'id'   => $this->classifiedCategory->id,
                'name' => Bilingual::pair($this->classifiedCategory, 'name'),
                'slug' => $this->classifiedCategory->slug,
            ]),
            'images'                  => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => Storage::url($img->file_path),
                'position'   => $img->position,
                'is_primary' => $img->is_primary,
            ])),
            'sketch_file_url'         => $this->sketch_file_path
                ? Storage::url($this->sketch_file_path)
                : null,
            'attachments'             => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($att) => [
                'id'              => $att->id,
                'attachment_type' => $att->attachment_type,
                'url'             => Storage::url($att->file_path),
                'status'          => $att->status?->value,
            ])),
            'contract'                => $this->when(
                in_array($this->status, [
                    \App\Enums\ClassifiedListingStatus::PendingContract,
                    \App\Enums\ClassifiedListingStatus::PendingReview,
                    \App\Enums\ClassifiedListingStatus::Active,
                ], true),
                fn () => [
                    'accepted_at'    => $this->contract_accepted_at?->toIso8601String(),
                    'has_signature'  => (bool) $this->contract_signature_data,
                ]
            ),
            'inquiries_count'         => $this->whenLoaded('inquiries', fn () => $this->inquiries->count()),
        ];
    }
}
