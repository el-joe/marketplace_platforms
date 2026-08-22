<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customerName = $this->whenLoaded('customer', fn () => $this->customer?->name);

        return [
            'id' => $this->id,
            'customer' => $customerName ? $this->maskName($customerName) : null,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'discount_amount' => (float) $this->discount_amount,
            'used_at' => $this->used_at?->toIso8601String(),
        ];
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));

        return implode(' ', array_map(
            fn (string $part) => mb_substr($part, 0, 1) . str_repeat('*', max(1, mb_strlen($part) - 1)),
            $parts
        ));
    }
}
