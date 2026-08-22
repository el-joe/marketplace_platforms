<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarrierAgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'vehicle_type'  => $this->vehicle_type?->value,
            'license_plate' => $this->vehicle_plate,
            'status'        => $this->status?->value,
            'is_available'  => $this->is_available,
            'rating_avg'    => $this->rating_avg,
            'total_deliveries' => $this->total_deliveries,
            'zone'          => $this->whenLoaded('zone', fn () => [
                'id'   => $this->zone->id,
                'name' => $this->zone->name,
                'code' => $this->zone->code,
            ]),
            'documents'     => $this->whenLoaded('documents', fn () =>
                $this->documents->map(fn ($doc) => [
                    'id'            => $doc->id,
                    'document_type' => $doc->document_type?->value,
                    'status'        => $doc->status?->value,
                    'expires_at'    => $doc->expires_at?->toDateString(),
                    'verified_at'   => $doc->verified_at?->toIso8601String(),
                ])
            ),
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
