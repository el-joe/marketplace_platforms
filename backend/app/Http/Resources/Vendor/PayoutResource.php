<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'payout_number'   => $this->payout_number,
            'period_start'    => $this->period_start?->toDateString(),
            'period_end'      => $this->period_end?->toDateString(),
            'gross_sales'     => $this->gross_sales,
            'net_amount'      => $this->net_amount,
            'currency'        => $this->currency,
            'status'          => $this->status?->value,
            'payout_method'   => $this->payout_method,
            'processed_at'    => $this->processed_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
