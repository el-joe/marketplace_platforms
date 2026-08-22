<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelPackageDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $agency = $this->relationLoaded('agency') ? $this->agency : null;

        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'title'               => Bilingual::pair($this->resource, 'title'),
            'description'         => Bilingual::pair($this->resource, 'description'),
            'destination_country' => $this->destination_country,
            'destination_city'    => $this->destination_city,
            'price'         => $this->price,
            'price_formatted'     => number_format($this->price / 100, 2),
            'currency'            => $this->currency,
            'available_seats'     => $this->available_seats,
            'seats_remaining'     => $this->seatsRemaining(),
            'seats_booked'        => $this->seats_booked,
            'price_tiers'         => ($this->pricing_tiers_enabled && $this->show_pricing_tiers_to_customer && $this->relationLoaded('pricingTiers'))
                ? $this->pricingTiers->map(fn ($tier) => [
                    'travelers_count' => $tier->travelers_count,
                    'price'     => $tier->price,
                ])->values()
                : null,
            'duration_days'       => $this->duration_days,
            'duration_nights'     => $this->duration_nights,
            'departure_date'      => $this->departure_date?->toDateString(),
            'return_date'         => $this->return_date?->toDateString(),
            'inclusions'          => $this->relationLoaded('inclusions')
                ? $this->inclusions->map(fn ($i) => [
                    'id'   => $i->id,
                    'name' => $i->name,
                    'icon' => $i->icon,
                ])->values()
                : [],
            'images'              => $this->relationLoaded('media')
                ? $this->media->map(fn ($m) => [
                    'id'       => $m->id,
                    'url'      => asset('storage/' . $m->file_path),
                    'position' => $m->position,
                ])->values()
                : [],
            'categories'          => $this->relationLoaded('categories')
                ? $this->categories->map(fn ($c) => [
                    'id'   => $c->id,
                    'name' => Bilingual::pair($c, 'name'),
                    'slug' => $c->slug,
                ])->values()
                : [],
            'agency'              => $agency ? [
                'id'             => $agency->id,
                'name'           => $agency->name,
                'logo_url'       => $agency->logoUrl(),
                'license_number' => $agency->license_number,
            ] : null,
            'status'              => $this->status?->value,
        ];
    }
}
