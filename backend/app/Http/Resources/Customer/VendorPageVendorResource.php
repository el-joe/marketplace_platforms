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
            'store_rating_avg' => $this->store_rating_avg,
            'store_rating_count' => $this->store_rating_count,
            'country' => $this->relationLoaded('country') && $this->country
                ? Bilingual::pair($this->country, 'name')
                : null,
        ];
    }
}
