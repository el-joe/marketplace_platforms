<?php

namespace App\Http\Resources\Delivery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order    = $this->subOrder?->order;
        $isCod    = $order?->payment_method === 'cod';
        $snapshot = $order?->shipping_address_snapshot ?? [];

        return [
            'id'                     => $this->id,
            'sub_order_number'       => $this->subOrder?->sub_order_number,
            'status'                 => $this->status?->value,
            'is_cod'                 => $isCod,
            'cod_amount'             => $isCod ? $order->total : null,
            'currency'               => $order?->currency,
            'delivery_otp'           => $this->delivery_otp,
            'otp_verified'           => (bool) $this->otp_verified,
            'assigned_at'            => $this->assigned_at?->toIso8601String(),
            'accepted_at'            => $this->accepted_at?->toIso8601String(),
            'picked_up_at'           => $this->picked_up_at?->toIso8601String(),
            'recipient_name'         => $this->maskName($snapshot['recipient_name'] ?? null),
            'recipient_phone_masked' => $this->maskPhone($snapshot['recipient_phone'] ?? null),
            'delivery_address'       => $this->formatAddress($snapshot),
            'latitude'               => $snapshot['latitude'] ?? null,
            'longitude'              => $snapshot['longitude'] ?? null,
            'sla_deadline'           => null,
            'shipment' => $this->whenLoaded('shipment', fn () => [
                'tracking_number' => $this->shipment->tracking_number,
                'carrier'         => $this->shipment->carrier?->name,
            ]),
        ];
    }

    // Mask to first name + last initial, e.g. "Ahmed Ali" -> "Ahmed A."
    private function maskName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($name));

        if (count($parts) === 1) {
            return $parts[0];
        }

        $lastInitial = mb_strtoupper(mb_substr($parts[array_key_last($parts)], 0, 1));

        return $parts[0] . ' ' . $lastInitial . '.';
    }

    // Keep first 4 and last 3 characters, mask the rest, e.g. "+971501234567" -> "+971*****567"
    private function maskPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $length = strlen($phone);

        if ($length <= 7) {
            return $phone;
        }

        $maskLength = max(3, $length - 7);

        return substr($phone, 0, 4) . str_repeat('*', $maskLength) . substr($phone, -3);
    }

    private function formatAddress(array $snapshot): ?string
    {
        $parts = array_filter([
            $snapshot['apartment'] ?? null,
            $snapshot['building'] ?? null,
            $snapshot['area'] ?? ($snapshot['street_address'] ?? null),
            $snapshot['city'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}
