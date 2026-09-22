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
            'subtotal' => (int) $this->subtotal,
            'shipping' => (int) $this->shipping,
            'tax' => (int) $this->tax,
            'vendor_coupon_cost' => (int) $this->vendor_coupon_cost,
            'vendor_contribution_amount' => (int) $this->vendor_contribution_amount,
            'gateway_fee' => (int) $this->gateway_fee,
            'vendor_payout' => (int) $this->vendor_payout,
            'order_total' => (int) $this->subtotal
                - (int) $this->vendor_coupon_cost
                + (int) $this->shipping
                - (int) $this->vendor_contribution_amount
                + (int) $this->tax,
            'marketer_commission_owner' => $this->marketer_commission_owner,
            'marketer_commission' => $this->marketer_commission_owner === 'vendor'
                ? (int) $this->marketer_commission
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
