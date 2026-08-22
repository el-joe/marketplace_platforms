<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cart_id'  => $this->id,
            'session_token' => $this->when(
                $this->session_token !== null,
                $this->session_token
            ),
            'currency' => $this->currency,
            'summary'  => [
                'subtotal'           => $this->subtotal,
                'discount'           => $this->discount,
                'wallet_amount_to_use' => $this->wallet_amount_to_use,
                'estimated_shipping' => $this->estimated_shipping,
                'estimated_tax'      => $this->estimated_tax,
                'estimated_total'    => $this->estimated_total,
                'item_count'               => $this->items->count(),
            ],
            'coupon' => $this->coupon ? [
                'code'        => $this->coupon->code,
                'type'        => $this->coupon->type?->value,
                'description' => $this->coupon->description,
            ] : null,
            'items'      => CartItemResource::collection($this->items),
            'expires_at' => $this->expires_at,
        ];
    }
}
