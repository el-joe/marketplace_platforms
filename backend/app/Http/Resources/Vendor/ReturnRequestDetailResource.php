<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestDetailResource extends JsonResource
{
    // $this->resource is the array produced by ReturnService::getDetail(),
    // merged with the loaded relations passed from the controller.

    public function __construct(
        private readonly array $detail,
        private readonly \App\Models\ReturnRequest $return
    ) {
        parent::__construct($detail);
    }

    public function toArray(Request $request): array
    {
        return array_merge($this->detail, [
            'items' => $this->return->relationLoaded('items')
                ? $this->return->items->map(fn ($ri) => [
                    'quantity'           => $ri->quantity,
                    'condition_received' => $ri->condition_received?->value,
                    'restock_decision'   => $ri->restock_decision?->value,
                    'product'            => $ri->orderItem?->product_snapshot ?? [],
                ])
                : [],

            'messages' => $this->return->relationLoaded('messages')
                ? ReturnRequestMessageResource::collection(
                    $this->return->messages->where('is_internal_note', false)->values()
                )
                : [],
        ]);
    }
}
