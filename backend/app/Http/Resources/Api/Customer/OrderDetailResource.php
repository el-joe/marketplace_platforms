<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status?->value,
            'currency' => $this->currency,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'summary' => [
                'subtotal' => $this->subtotal,
                'discount' => $this->discount,
                'shipping' => $this->shipping,
                'tax' => $this->tax,
                'cod_fee' => $this->cod_fee,
                'warranty_total' => $this->warranty_total,
                'total' => $this->total,
                'coupon_code_used' => $this->coupon_code_used,
            ],
            'shipping_address' => $this->shipping_address_snapshot,
            'sub_orders' => $this->subOrders->map(fn (SubOrder $subOrder) => (new SubOrderDetailResource($subOrder))->toArray($request)),
            'status_history' => $this->statusHistories->map(fn (OrderStatusHistory $history) => [
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'reason' => $history->reason,
                'created_at' => $history->created_at?->toIso8601String(),
            ]),
        ];
    }
}
