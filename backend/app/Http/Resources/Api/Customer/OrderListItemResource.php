<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'currency' => $this->currency,
            'total' => $this->total,
            'sub_orders_count' => $this->sub_orders_count,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'sub_orders' => $this->whenLoaded('subOrders', fn () =>
                $this->subOrders->map(fn (SubOrder $subOrder) => [
                    'sub_order_number' => $subOrder->sub_order_number,
                    'shipping_badge' => $subOrder->shippingMethod?->badge_label_en ? [
                        'label_en' => $subOrder->shippingMethod->badge_label_en,
                        'label_ar' => $subOrder->shippingMethod->badge_label_ar,
                        'color_hex' => $subOrder->shippingMethod->badge_color_hex,
                    ] : null,
                ])
            ),
        ];
    }
}
