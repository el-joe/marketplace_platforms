<?php

namespace App\Http\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order    = $this->subOrder?->order;
        $isCod    = $order?->payment_method === 'cod';
        $snapshot = $order?->shipping_address_snapshot ?? [];

        return [
            'id'                => $this->id,
            'sub_order_number'  => $this->subOrder?->sub_order_number,
            'status'            => $this->status?->value,
            'is_cod'            => $isCod,
            'currency'          => $order?->currency,
            'agent_currency'    => $this->subOrder?->agent?->country?->currency_code,
            'failure_reason' => $this->failure_reason,
            'failure_notes'  => $this->failure_notes,
            'customer_rating' => $this->customer_rating,
            'agent_notes'    => $this->agent_notes,
            'proof_file_id'  => $this->proof_file_id,
            'cod_amount_collected' => $this->cod_amount_collected,
            'assigned_at'    => $this->assigned_at?->toIso8601String(),
            'accepted_at'    => $this->accepted_at?->toIso8601String(),
            'picked_up_at'   => $this->picked_up_at?->toIso8601String(),
            'delivered_at'   => $this->delivered_at?->toIso8601String(),
            'failed_at'      => $this->failed_at?->toIso8601String(),

            // OTP is NEVER returned — verified server-side only.

            'otp_verified'   => $this->otp_verified,
            'otp_attempts'   => $this->otp_attempts,
            'otp_locked'     => $this->otp_attempts >= 3 && ! $this->otp_verified,

            'cod_amount_due' => $isCod ? $order->total : null,

            'pickup_location' => [
                'latitude'  => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ],
            'delivery_location' => [
                'latitude'  => $this->delivery_latitude,
                'longitude' => $this->delivery_longitude,
            ],

            // Full delivery address — agent needs this to find the customer.
            'delivery_address' => [
                'recipient_name'  => $snapshot['recipient_name'] ?? null,
                'recipient_phone' => $snapshot['recipient_phone'] ?? null,
                'street_address'  => $snapshot['street_address'] ?? null,
                'area'            => $snapshot['area'] ?? null,
                'building'        => $snapshot['building'] ?? null,
                'floor'           => $snapshot['floor'] ?? null,
                'apartment'       => $snapshot['apartment'] ?? null,
                'landmark'        => $snapshot['landmark'] ?? null,
                'city'            => $snapshot['city'] ?? null,
                'latitude'        => $snapshot['latitude'] ?? null,
                'longitude'       => $snapshot['longitude'] ?? null,
            ],

            'items' => $this->whenLoaded('subOrder', function () {
                return $this->subOrder->items->map(fn ($item) => [
                    'name'     => $item->product_snapshot['name'] ?? $item->sku,
                    'quantity' => $item->quantity,
                    'image'    => $item->product_snapshot['image'] ?? null,
                    // No prices — agent doesn't need to know order value.
                ]);
            }),

            'shipment' => $this->whenLoaded('shipment', fn () => [
                'id'              => $this->shipment->id,
                'tracking_number' => $this->shipment->tracking_number,
                'status'          => $this->shipment->status?->value,
                'carrier'         => $this->shipment->carrier?->name,
            ]),
        ];
    }
}
