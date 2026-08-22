<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorCouponUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_reference' => $this->maskOrderReference($this->whenLoaded('order', fn () => $this->order?->order_number)),
            'discount_amount' => (float) $this->discount_amount,
            'used_at' => $this->used_at?->toIso8601String(),
        ];
    }

    private function maskOrderReference(mixed $reference): ?string
    {
        if (!is_string($reference) || $reference === '') {
            return null;
        }

        $visible = max(1, (int) ceil(mb_strlen($reference) * 0.4));
        $masked = mb_substr($reference, 0, $visible);

        return $masked . str_repeat('*', mb_strlen($reference) - $visible);
    }
}
