<?php

namespace App\Http\Resources\Vendor;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdPerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date'                      => $this->date instanceof \Carbon\Carbon
                                            ? $this->date->toDateString()
                                            : $this->date,
            'vendor_listing_id'         => $this->vendor_listing_id,
            'impressions'               => (int) $this->impressions,
            'clicks'                    => (int) $this->clicks,
            'valid_clicks'              => (int) $this->valid_clicks,
            'conversions'               => (int) $this->conversions,
            'spend'               => Money::number($this->spend),
            'revenue_attributed'  => Money::number($this->revenue_attributed),
            'ctr'                       => (float) $this->ctr,
            'cvr'                       => (float) $this->cvr,
            'acos'                      => (float) $this->acos,
            'average_position'          => (float) $this->average_position,
        ];
    }
}
