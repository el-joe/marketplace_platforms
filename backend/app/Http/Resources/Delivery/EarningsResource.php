<?php

namespace App\Http\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EarningsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'earning_type'    => $this->earning_type,
            'amount'          => $this->amount,
            'currency'        => $this->currency,
            'status'          => $this->status?->value,
            'earned_at'       => $this->created_at?->toIso8601String(),
            'assignment_id'   => $this->delivery_assignment_id,
        ];
    }
}
