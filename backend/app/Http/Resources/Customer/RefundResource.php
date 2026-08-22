<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'order_number' => $this->order?->order_number,
            'amount'      => $this->amount / 100,
            'currency'    => $this->currency,
            'reason'      => $this->reason?->value,
            'refund_type' => $this->refund_type?->value,
            'status'      => $this->status?->value,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
