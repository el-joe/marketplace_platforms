<?php

namespace App\Http\Resources\Customer;

use App\Models\Vendor;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassifiedListingPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'listing_number'   => $this->listing_number,
            'title'            => Bilingual::pair($this->resource, 'title'),
            'price'      => $this->price,
            'price_negotiable' => $this->price_negotiable,
            'primary_image'    => $this->primary_image_url,
            'city'             => $this->relationLoaded('city') ? Bilingual::pair($this->city, 'name') : null,
            'seller_label'     => $this->sellerLabel(),
            'listing_purpose'  => $this->listing_purpose,
            'views_count'      => $this->views_count,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }

    private function sellerLabel(): string
    {
        if ($this->seller_type === Vendor::class) {
            if ($this->relationLoaded('seller') && $this->seller) {
                return $this->seller->store_name;
            }
            return 'Vendor';
        }

        return 'Individual';
    }
}
