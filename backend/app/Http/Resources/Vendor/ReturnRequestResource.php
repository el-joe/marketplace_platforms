<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'return_number'      => $this->return_number,
            'sub_order_number'   => $this->subOrder?->sub_order_number,
            'reason'             => $this->reason?->value,
            'reason_description' => $this->reason_description,
            'return_type'        => $this->return_type?->value,
            'status'             => $this->status?->value,
            'refund_amount' => $this->refund_amount,
            'rejection_reason'   => $this->rejection_reason,
            'inspection_result'  => $this->inspection_result?->value,
            'inspection_notes'   => $this->inspection_notes,
            'liability'          => $this->liability?->value,
            'created_at'         => $this->created_at->toIso8601String(),

            'items' => $this->whenLoaded('items', fn () =>
                $this->items->map(fn ($ri) => [
                    'order_item_id'      => $ri->order_item_id,
                    'quantity'           => $ri->quantity,
                    'condition_received' => $ri->condition_received?->value,
                    'restock_decision'   => $ri->restock_decision?->value,
                    // Snapshot from order item — never live product data
                    'product'            => $ri->orderItem?->product_snapshot ?? [],
                ])
            ),
        ];
    }
}
