<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClassifiedListingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return [
            'id'             => $this->id,
            'listing_number' => $this->listing_number,
            'title_en'       => $this->title_en,
            'title_ar'       => $this->title_ar,
            'price'    => $this->price,
            'currency'       => $this->currency,
            'status'         => $this->status?->value,
            'views_count'    => $this->views_count,
            'primary_image'  => $primaryImage ? Storage::url($primaryImage->file_path) : null,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
