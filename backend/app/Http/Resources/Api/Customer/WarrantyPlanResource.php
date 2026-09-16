<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => ['ar' => $this->name_ar, 'en' => $this->name_en],
            'duration_months' => $this->duration_months,
            'duration_label' => $this->formatDurationLabel($this->duration_months),
            'features'       => ['ar' => $this->features_ar, 'en' => $this->features_en],
            'price'          => $this->price,
            'currency'       => $this->currency,
            'image_url'      => $this->image_url,
        ];
    }

    private function formatDurationLabel(int $months): array
    {
        return [
            'ar' => match (true) {
                $months === 1  => 'شهر واحد',
                $months === 6  => '6 أشهر',
                $months === 12 => 'سنة واحدة',
                $months === 24 => 'سنتان',
                $months <= 11  => "{$months} أشهر",
                $months % 12 === 0 => ($months / 12).' سنوات',
                default        => "{$months} أشهر",
            },
            'en' => match (true) {
                $months === 1  => '1 month',
                $months === 6  => '6 months',
                $months === 12 => '1 year',
                $months === 24 => '2 years',
                $months <= 11  => "{$months} months",
                $months % 12 === 0 => ($months / 12).' years',
                default        => "{$months} months",
            },
        ];
    }
}
