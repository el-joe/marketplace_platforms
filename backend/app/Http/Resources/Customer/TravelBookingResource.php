<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TravelBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'booking_number'  => $this->booking_number,
            'status'          => $this->status?->value,
            'travelers_count' => $this->travelers_count,
            'total_price' => $this->total_price,
            'currency' => $this->whenLoaded('package', fn () => $this->package->currency),
            'total_price_formatted' => $this->whenLoaded('package', fn () => $this->totalFormatted()),
            'passport_uploaded' => (bool) $this->passport_file_path,
            'contract_signed_at' => $this->contract_signed_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'package'         => $this->whenLoaded('package', fn () => [
                'id'          => $this->package->id,
                'title'       => Bilingual::pair($this->package, 'title'),
                'price' => $this->package->price,
                'currency'    => $this->package->currency,
                'agency'      => $this->when(
                    $this->package->relationLoaded('agency'),
                    fn () => [
                        'id'   => $this->package->agency?->id,
                        'name' => $this->package->agency?->name,
                    ]
                ),
                'cover_image' => $this->when(
                    $this->package->relationLoaded('media'),
                    fn () => optional($this->package->coverImage())->file_path
                        ? Storage::url($this->package->coverImage()->file_path)
                        : null
                ),
            ]),
        ];
    }
}
