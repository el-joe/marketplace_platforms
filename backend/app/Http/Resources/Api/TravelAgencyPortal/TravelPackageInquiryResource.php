<?php

namespace App\Http\Resources\Api\TravelAgencyPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelPackageInquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'travelers_count' => $this->travelers_count,
            'message' => $this->message,
            'status' => $this->status?->value,
            'close_reason' => $this->close_reason,
            'converted_to_booking_id' => $this->converted_to_booking_id,
            'contacted_at' => $this->contacted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'package' => $this->whenLoaded('package', fn () => [
                'id' => $this->package->id,
                'title_en' => $this->package->title_en,
                'title_ar' => $this->package->title_ar,
            ]),
        ];
    }
}
