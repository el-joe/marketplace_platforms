<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'return_number'     => $this->return_number,
            'order_number'      => $this->order?->order_number,
            'reason'            => $this->reason?->value,
            'reason_description' => $this->reason_description,
            'return_type'       => $this->return_type?->value,
            'status'            => $this->status?->value,
            'refund_amount'     => $this->refund_amount !== null ? $this->refund_amount / 100 : null,
            'rejection_reason'  => $this->rejection_reason,
            'created_at'        => $this->created_at?->toIso8601String(),
            'items'             => $this->whenLoaded('items', fn () =>
                $this->items->map(fn ($i) => [
                    'order_item_id'   => $i->order_item_id,
                    'quantity'        => $i->quantity,
                    'product_snapshot' => $i->orderItem?->product_snapshot,
                ])
            ),
        ];
    }
}
