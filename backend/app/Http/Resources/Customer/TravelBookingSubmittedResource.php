<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelBookingSubmittedResource extends JsonResource
{
    public ?string $currency = null;

    public ?string $pendingMessage = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'status' => $this->status?->value,
            'travelers_count' => $this->travelers_count,
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'created_at' => $this->created_at->toIso8601String(),
            'message' => $this->pendingMessage,
        ];
    }
}
