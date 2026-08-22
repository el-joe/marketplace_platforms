<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->customer;
        $first    = $customer?->first_name ?? '';
        $last     = $customer?->last_name  ?? '';
        $masked   = trim($first . ' ' . ($last ? strtoupper(substr($last, 0, 1)) . '.' : ''));

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
