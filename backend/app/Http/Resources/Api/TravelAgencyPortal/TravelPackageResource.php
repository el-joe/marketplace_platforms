<?php

namespace App\Http\Resources\Api\TravelAgencyPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'destination_travel_country_id' => $this->destination_travel_country_id,
            'destination_travel_city_id' => $this->destination_travel_city_id,
            'price' => $this->price,
            'price_formatted' => $this->priceFormatted(),
            'currency' => $this->currency,
            'pricing_tiers_enabled' => (bool) $this->pricing_tiers_enabled,
            'show_pricing_tiers_to_customer' => (bool) $this->show_pricing_tiers_to_customer,
            'price_tiers' => $this->whenLoaded('pricingTiers', fn () => $this->pricingTiers->map(fn ($tier) => [
                'travelers_count' => $tier->travelers_count,
                'price' => $tier->price,
            ])),
            'duration_days' => $this->duration_days,
            'duration_nights' => $this->duration_nights,
            'departure_date' => $this->departure_date?->toDateString(),
            'return_date' => $this->return_date?->toDateString(),
            'available_seats' => $this->available_seats,
            'seats_booked' => $this->seats_booked,
            'seats_remaining' => $this->seatsRemaining(),
            'inclusions' => $this->whenLoaded('inclusions', fn () => $this->inclusions->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'icon' => $i->icon,
            ])),
            'status' => $this->status?->value,
            'rejection_reason' => $this->rejection_reason,
            'contract_uploaded' => (bool) $this->contract_file_path,
            'contract_uploaded_at' => $this->contract_uploaded_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'destination_country' => $this->whenLoaded('destinationCountry', fn () => $this->destinationCountry ? [
                'id' => $this->destinationCountry->id,
                'name' => $this->destinationCountry->name_en,
            ] : null),
            'destination_city' => $this->whenLoaded('destinationCity', fn () => $this->destinationCity ? [
                'id' => $this->destinationCity->id,
                'name' => $this->destinationCity->name_en,
            ] : null),
            'media' => TravelPackageMediaResource::collection($this->whenLoaded('media')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
