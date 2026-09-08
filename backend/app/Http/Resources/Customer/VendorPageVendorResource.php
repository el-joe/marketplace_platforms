<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorPageVendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'logo_url' => $this->avatar,
            'store_rating_avg' => $this->store_rating_avg,
            'store_rating_count' => $this->store_rating_count,
            'positive_rating_pct' => $this->positive_rating_pct,
            'seller_since' => $this->created_at?->toDateString(),
            'country' => $this->relationLoaded('country') && $this->country
                ? Bilingual::pair($this->country, 'name')
                : null,
            'address' => $this->relationLoaded('businessAddress') && $this->businessAddress
                ? [
                    'area' => $this->businessAddress->area,
                    'street_address' => $this->businessAddress->street_address,
                    'city' => $this->businessAddress->relationLoaded('city') && $this->businessAddress->city
                        ? Bilingual::pair($this->businessAddress->city, 'name')
                        : null,
                ]
                : null,
        ];
    }
}
