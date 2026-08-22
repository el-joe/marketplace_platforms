<?php

namespace App\Http\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'shift_date'           => $this->shift_date?->toDateString(),
            'zone_id'              => $this->zone_id,
            'status'               => $this->status->value,
            'actual_start'         => $this->actual_start?->toIso8601String(),
            'actual_end'           => $this->actual_end?->toIso8601String(),
            'duration_minutes'     => $this->duration_minutes,
            'total_deliveries'     => $this->total_deliveries,
            'total_earnings'       => (int) $this->getRawOriginal('total_earnings'),
        ];
    }
}
