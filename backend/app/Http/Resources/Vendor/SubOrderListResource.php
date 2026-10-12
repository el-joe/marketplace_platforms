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
            'sub_order_number' => $this->sub_order_number,
            'order_id' => $this->order_id,
            'item_count' => $this->items_count ?? $this->items->count(),
            'subtotal' => \App\Support\Money::number($this->subtotal),
            'shipping' => \App\Support\Money::number($this->shipping),
            'tax' => \App\Support\Money::number($this->tax),
            'vendor_coupon_cost' => \App\Support\Money::number($this->vendor_coupon_cost),
            'vendor_contribution_amount' => \App\Support\Money::number($this->vendor_contribution_amount),
            'gateway_fee' => \App\Support\Money::number($this->gateway_fee),
            'vendor_payout' => \App\Support\Money::number($this->vendor_payout),
            'order_total' => \App\Support\Money::number($this->subtotal)
                - \App\Support\Money::number($this->vendor_coupon_cost)
                + \App\Support\Money::number($this->shipping)
                - \App\Support\Money::number($this->vendor_contribution_amount)
                + \App\Support\Money::number($this->tax),
            'marketer_commission_owner' => $this->marketer_commission_owner,
            'marketer_commission' => $this->marketer_commission_owner === 'vendor'
                ? \App\Support\Money::number($this->marketer_commission)
                : null,
            'currency' => $this->order?->currency,
            'status' => $this->status?->value,
            'fulfillment_model' => $this->fulfillment_model,
            'cod_remittance_confirmed' => (bool) $this->cod_remittance_confirmed,
            'tracking_number' => $this->tracking_number,
            'sla_ship_deadline' => $this->sla_ship_deadline?->toIso8601String(),
            'sla_breached' => (bool) $this->sla_breached,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'placed_at' => $this->order?->placed_at?->toIso8601String(),
            // City only at list level — customer name/phone withheld
            'customer_city' => $address['city'] ?? null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
