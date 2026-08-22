<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'type'              => $this->type?->value,
            'is_active'         => (bool) $this->is_active,
            'total_capacity_m3' => $this->total_capacity_m3 ? (float) $this->total_capacity_m3 : null,
            'used_capacity_m3'  => $this->used_capacity_m3 ? (float) $this->used_capacity_m3 : null,
            'country'           => $this->whenLoaded('country', fn () => [
                'id'   => $this->country->id,
                'name' => $this->country->name,
            ]),
            'address'           => $this->whenLoaded('address', fn () => $this->address ? [
                'area'           => $this->address->area,
                'street_address' => $this->address->street_address,
                'building'       => $this->address->building,
            ] : null),
            'created_at'        => $this->created_at->toIso8601String(),
            'updated_at'        => $this->updated_at->toIso8601String(),
        ];
    }
}
