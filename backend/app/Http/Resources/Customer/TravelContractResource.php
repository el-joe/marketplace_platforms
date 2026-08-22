<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'contract_signed_at' => $this->contract_signed_at?->toIso8601String(),
            'status' => $this->status?->value,
        ];
    }
}
