<?php

namespace App\Http\Resources\Delivery;

use App\Enums\DeliveryAgentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryAgentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'status'           => $this->status->value,
            'agent_type'       => $this->agent_type?->value,
            'vehicle_type'     => $this->vehicle_type?->value,
            'vehicle_plate'    => $this->vehicle_plate,
            'is_available'     => $this->is_available,
            'is_on_shift'      => $this->status === DeliveryAgentStatus::OnShift,
            'rating_avg'       => $this->rating_avg,
            'total_deliveries' => $this->total_deliveries,
            'per_delivery_fee' => $this->per_delivery_fee,
            'currency'         => $this->country?->currency_code
                ?? $this->zone?->country?->currency_code
                ?? 'AED',
            'shipping_company_id'   => $this->shipping_company_id,
            'shipping_company_name' => $this->whenLoaded('shippingCompany', fn () => $this->shippingCompany?->name),
            'current_location' => $this->current_latitude !== null ? [
                'latitude'    => $this->current_latitude,
                'longitude'   => $this->current_longitude,
                'recorded_at' => $this->last_location_at?->toIso8601String(),
            ] : null,
            'zone'             => $this->whenLoaded('zone', fn () => [
                'id'                => $this->zone->id,
                'name'              => $this->zone->name,
                'base_delivery_fee' => $this->zone->base_delivery_fee,
                'cod_fee'           => $this->zone->cod_fee,
            ]),
            'country'          => $this->whenLoaded('country', fn () => [
                'id'   => $this->country->id,
                'name' => $this->country->name_en,
            ]),
            'today_stats'      => $this->when(
                isset($this->todayStats),
                fn () => array_merge($this->todayStats, [
                    'currency' => $this->country?->currency_code
                        ?? $this->zone?->country?->currency_code
                        ?? 'AED',
                ])
            ),
            'last_login_at'    => $this->last_login_at?->toIso8601String(),
        ];
    }
}
