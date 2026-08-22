<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QualityScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'score'           => (float) $this->score,
            'ctr_score'       => (float) $this->ctr_score,
            'relevance_score' => (float) $this->relevance_score,
            'landing_score'   => (float) $this->landing_score,
            'vendor_score'    => (float) $this->vendor_score,
            'calculated_at'   => $this->calculated_at?->toISOString(),
        ];
    }
}
