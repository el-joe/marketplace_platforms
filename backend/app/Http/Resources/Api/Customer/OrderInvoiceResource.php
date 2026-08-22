<?php

namespace App\Http\Resources\Api\Customer;

use App\Models\OrderItem;
use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status?->value,
            'shipping_address' => $this->shipping_address_snapshot,
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
            'sub_orders' => $this->subOrders->map(fn (SubOrder $subOrder) => [
                'sub_order_number' => $subOrder->sub_order_number,
                'vendor_name' => $subOrder->vendor?->store_name,
                'subtotal' => $subOrder->subtotal,
                'shipping' => $subOrder->shipping,
                'tax' => $subOrder->tax,
                'items' => $subOrder->items->map(fn (OrderItem $item) => [
                    'sku' => $item->sku,
                    'name_en' => $item->product_snapshot['name_en'] ?? null,
                    'name_ar' => $item->product_snapshot['name_ar'] ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_subtotal' => $item->line_subtotal,
                    'line_discount' => $item->line_discount,
                    'line_tax' => $item->line_tax,
                    'line_total' => $item->line_total,
                ]),
            ]),
        ];
    }
}
