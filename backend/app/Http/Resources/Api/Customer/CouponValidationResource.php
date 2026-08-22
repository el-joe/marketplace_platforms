<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'valid' => true,
            'discount_type' => $this->type?->value,
            'discount_value' => $this->value,
            'max_discount' => $this->max_discount,
            'min_order_amount' => $this->min_order_amount,
            'description' => $this->description,
        ];
    }
}
