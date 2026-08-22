<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CustomerClassifiedListingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return [
            'id'             => $this->id,
            'listing_number' => $this->listing_number,
            'title'          => Bilingual::pair($this->resource, 'title'),
            'price'    => $this->price,
            'currency'       => $this->currency,
            'status'         => $this->status?->value,
            'views_count'    => $this->views_count,
            'primary_image'  => $primaryImage ? Storage::url($primaryImage->file_path) : null,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
