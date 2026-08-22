<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\OrderItem;
use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'total' => $this->total,
            'currency' => $this->currency,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'sub_orders' => $this->subOrders->map(fn (SubOrder $so) => [
                'sub_order_number' => $so->sub_order_number,
                'status' => $so->status?->value ?? $so->status,
                'shipping' => $so->shipping,
                'items' => $so->items->map(fn (OrderItem $item) => [
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
            ]),
        ];
    }
}
