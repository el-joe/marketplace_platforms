<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelPackagePublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryMedia = $this->relationLoaded('media')
            ? $this->media->first()
            : null;

        $seatsRemaining = $this->seatsRemaining();

        return [
            'id'                  => $this->id,
            'title'               => Bilingual::pair($this->resource, 'title'),
            'destination_country' => $this->destination_country,
            'destination_city'    => $this->destination_city,
            'price'         => $this->price,
            'duration_days'       => $this->duration_days,
            'duration_nights'     => $this->duration_nights,
            'departure_date'      => $this->departure_date?->toDateString(),
            'return_date'         => $this->return_date?->toDateString(),
            'seats_remaining'     => $seatsRemaining,
            'primary_image'       => $primaryMedia?->url(),
            'agency_name'         => $this->relationLoaded('agency') ? $this->agency?->name : null,
        ];
    }
}
