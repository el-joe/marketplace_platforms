<?php

namespace App\Http\Resources\Api\TravelAgencyPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'status' => $this->status?->value,
            'travelers_count' => $this->travelers_count,
            'total_price' => $this->total_price,
            'currency' => $this->whenLoaded('package', fn () => $this->package->currency),
            'total_price_formatted' => $this->whenLoaded('package', fn () => $this->totalFormatted()),
            'passport_uploaded' => (bool) $this->passport_file_path,
            'contract_signed_at' => $this->contract_signed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'package' => $this->whenLoaded('package', fn () => [
                'id' => $this->package->id,
                'title_en' => $this->package->title_en,
                'title_ar' => $this->package->title_ar,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
        ];
    }
}
