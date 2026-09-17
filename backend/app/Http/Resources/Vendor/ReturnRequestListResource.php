<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $masked = \App\Models\Customer::maskName($this->customer?->name);

        return [
            'return_number'       => $this->return_number,
            'order_number_masked' => $this->order ? ('****' . substr($this->order->order_number, -4)) : null,
            'customer_name'       => $masked ?: null,
            'reason'              => $this->reason?->value,
            'return_type'         => $this->return_type?->value,
            'status'              => $this->status?->value,
            'created_at'          => $this->created_at->toIso8601String(),
        ];
    }
}
