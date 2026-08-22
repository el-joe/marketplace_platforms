<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'country_id' => $this->country_id,
            'city_id' => $this->city_id,
            'area' => $this->area,
            'street_address' => $this->street_address,
            'building' => $this->building,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
            'postal_code' => $this->postal_code,
            'landmark' => $this->landmark,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => (bool) $this->is_default,
            'address_type' => $this->address_type?->value,
            'full_address' => $this->buildFullAddress(),
        ];
    }

    private function buildFullAddress(): string
    {
        $parts = array_filter([
            $this->building ? "Bldg {$this->building}" : null,
            $this->floor ? "Floor {$this->floor}" : null,
            $this->apartment ? "Apt {$this->apartment}" : null,
            $this->street_address,
            $this->area,
            $this->landmark ? "(Near {$this->landmark})" : null,
        ]);

        return implode(', ', $parts);
    }
}
