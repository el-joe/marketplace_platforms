<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'movement_type'           => $this->movement_type?->value,
            'quantity_delta'          => (int) $this->quantity_delta,
            'quantity_after'          => (int) $this->quantity_after,
            'reference_type'          => $this->reference_type?->value,
            'reference_id'            => $this->reference_id,
            'reason'                  => $this->reason,
            'created_at'              => $this->created_at->toIso8601String(),
        ];
    }
}
