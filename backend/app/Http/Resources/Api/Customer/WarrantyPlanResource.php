<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id'             => $this->id,
            'name'           => $locale === 'ar' ? $this->name_ar : $this->name_en,
            'duration_months' => $this->duration_months,
            'duration_label' => $this->formatDurationLabel($this->duration_months),
            'features'       => $locale === 'ar' ? $this->features_ar : $this->features_en,
            'price'          => $this->price,
            'currency'       => $this->currency,
        ];
    }

    private function formatDurationLabel(int $months): string
    {
        return match (true) {
            $months === 1  => '1 month',
            $months === 6  => '6 months',
            $months === 12 => '1 year',
            $months === 24 => '2 years',
            $months <= 11  => "{$months} months",
            $months % 12 === 0 => ($months / 12).' years',
            default        => "{$months} months",
        };
    }
}
