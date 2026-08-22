<?php

namespace App\Http\Resources\Customer;

use App\Models\Vendor;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassifiedListingDetailResource extends JsonResource
{
    public array $sellerInfo = [];

    public function toArray(Request $request): array
    {
        return [
            'listing_number'   => $this->listing_number,
            'slug'             => $this->slug,
            'title'            => Bilingual::pair($this->resource, 'title'),
            'description'      => Bilingual::pair($this->resource, 'description'),
            'listing_purpose'  => $this->listing_purpose,
            'price'      => $this->price,
            'currency'         => $this->currency,
            'price_negotiable' => (bool) $this->price_negotiable,
            'attributes'       => $this->attributes ?? [],
            'location'         => [
                'city' => $this->relationLoaded('city') ? Bilingual::pair($this->city, 'name') : null,
                // lat/lng intentionally omitted from public detail — approximate area only
            ],
            'category'         => $this->relationLoaded('classifiedCategory') ? [
                'id'   => $this->classifiedCategory?->id,
                'name' => $this->classifiedCategory ? Bilingual::pair($this->classifiedCategory, 'name') : ['ar' => null, 'en' => null],
            ] : null,
            'images'           => $this->relationLoaded('images')
                ? $this->images->map(fn ($img) => [
                    'id'       => $img->id,
                    'url'      => asset('storage/' . $img->file_path),
                    'position' => $img->position,
                ])->values()
                : [],
            'seller'           => $this->sellerInfo,
            'views_count'      => $this->views_count,
            'expires_at'       => $this->expires_at?->toDateString(),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
