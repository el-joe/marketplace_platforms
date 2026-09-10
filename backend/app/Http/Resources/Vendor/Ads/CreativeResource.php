<?php

namespace App\Http\Resources\Vendor\Ads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreativeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'status' => [
                'value' => $this->status?->value,
                'label' => __('ads.creative_status.'.$this->status?->value),
            ],
            'images' => [
                'desktop' => $this->imagePair('desktop'),
                'mobile' => $this->imagePair('mobile'),
            ],
            'title' => ['en' => $this->title_en, 'ar' => $this->title_ar],
            'subtitle' => ['en' => $this->subtitle_en, 'ar' => $this->subtitle_ar],
            'cta_label' => ['en' => $this->cta_label_en, 'ar' => $this->cta_label_ar],
            'destination' => [
                'type' => $this->destination_type,
                'reference_id' => $this->destination_reference_id,
                'url' => $this->destination_url,
            ],
            'rejection_reason' => $this->rejection_reason,
            'rejection_code' => $this->rejection_code,
        ];
    }
}
