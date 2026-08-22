<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $address = $this->order?->shipping_address_snapshot ?? [];

        return [
            'sub_order_number'          => $this->sub_order_number,
            'order_id'                  => $this->order_id,
            'item_count'                => $this->items_count ?? $this->items->count(),
            'subtotal'            => (int) $this->subtotal,
            'total'               => (int) $this->subtotal,
            'shipping'            => (int) $this->shipping,
            'platform_commission' => (int) $this->platform_commission,
            'gateway_fee'         => (int) $this->gateway_fee,
            'vendor_payout'       => (int) $this->vendor_payout,
            'currency'                  => $this->order?->currency,
            'status'                    => $this->status?->value,
            'fulfillment_model'         => $this->fulfillment_model,
            'cod_remittance_confirmed'  => (bool) $this->cod_remittance_confirmed,
            'tracking_number'           => $this->tracking_number,
            'sla_ship_deadline'         => $this->sla_ship_deadline?->toIso8601String(),
            'sla_breached'              => (bool) $this->sla_breached,
            'shipped_at'                => $this->shipped_at?->toIso8601String(),
            'placed_at'                 => $this->order?->placed_at?->toIso8601String(),
            // City only at list level — customer name/phone withheld
            'customer_city'             => $address['city'] ?? null,
            'created_at'                => $this->created_at->toIso8601String(),
        ];
    }
}
