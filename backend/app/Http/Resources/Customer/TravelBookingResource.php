<?php

namespace App\Http\Resources\Customer;

use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'status' => $this->status?->value,
            'travelers_count' => $this->travelers_count,
            'total_price' => $this->total_price,
            'currency' => $this->whenLoaded('package', fn () => $this->package->currency),
            'total_price_formatted' => $this->whenLoaded('package', fn () => $this->totalFormatted()),
            'passport_uploaded' => (bool) $this->passport_file_path,
            'contract_signed_at' => $this->contract_signed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'package' => $this->whenLoaded('package', fn () => [
                'id' => $this->package->id,
                'title' => Bilingual::pair($this->package, 'title'),
                'price' => $this->package->price,
                'currency' => $this->package->currency,
                'agency' => $this->when(
                    $this->package->relationLoaded('agency'),
                    fn () => [
                        'id' => $this->package->agency?->id,
                        'name' => $this->package->agency?->name,
                    ]
                ),
                'cover_image' => $this->when(
                    $this->package->relationLoaded('media'),
                    fn () => $this->package->coverImage()?->url()
                ),
                'departure_date' => $this->package->departure_date?->toDateString(),
                'return_date' => $this->package->return_date?->toDateString(),
                'duration_days' => $this->package->duration_days,
                'duration_nights' => $this->package->duration_nights,
            ]),
            'bookable_unit' => $this->whenLoaded('bookableUnit', fn () => $this->bookableUnit ? [
                'id' => $this->bookableUnit->id,
                'name' => $this->bookableUnit->name,
                'name_ar' => $this->bookableUnit->name_ar,
                'type' => $this->bookableUnit->type,
                'capacity' => $this->bookableUnit->capacity,
                'primary_photo_url' => $this->bookableUnit->primary_photo_url,
            ] : null),
            'unit_days' => $this->whenLoaded('unitDays', fn () => $this->unitDays->map(fn ($day) => [
                'id' => $day->id,
                'date' => $day->date->toDateString(),
                'price' => $day->price,
                'includes_overnight' => $day->includes_overnight,
                'time_slot' => $day->timeSlot ? [
                    'id' => $day->timeSlot->id,
                    'slot_type' => $day->timeSlot->slot_type,
                    'starts_at' => $day->timeSlot->starts_at,
                    'ends_at' => $day->timeSlot->ends_at,
                ] : null,
            ])->sortBy('date')->values()),
            'unit_days_total' => $this->whenLoaded('unitDays', fn () => $this->unitDays->sum('price')),
        ];
    }
}
