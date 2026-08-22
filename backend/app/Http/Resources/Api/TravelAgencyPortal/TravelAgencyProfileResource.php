<?php

namespace App\Http\Resources\Api\TravelAgencyPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelAgencyProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'logo_url' => $this->logoUrl(),
            'status' => $this->status?->value,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country->id,
                'name' => $this->country->name_en,
            ]),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
