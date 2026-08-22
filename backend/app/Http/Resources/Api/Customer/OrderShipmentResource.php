<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'carrier_id' => $this->carrier_id,
            'tracking_number' => $this->tracking_number,
            'awb_label_url' => $this->awb_label_url,
            'weight_grams' => $this->weight_grams,
            'status' => $this->status?->value,
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'events' => $this->relationLoaded('trackingEvents')
                ? $this->trackingEvents->sortBy('occurred_at')->values()->map(fn ($event) => [
                    'status' => $event->status?->value,
                    'description' => $event->description,
                    'location' => $event->location,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])
                : [],
        ];
    }
}
