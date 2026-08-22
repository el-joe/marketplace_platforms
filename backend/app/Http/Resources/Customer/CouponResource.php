<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'             => $this->code,
            'name'             => $this->name,
            'description'      => $this->description,
            'type'             => $this->type->value,
            'value'            => $this->value,
            'min_order_amount' => $this->min_order_amount,
            'max_discount'     => $this->max_discount,
            'valid_until'      => $this->valid_until->toIso8601String(),
            'is_stackable'     => $this->is_stackable,
        ];
    }
}
