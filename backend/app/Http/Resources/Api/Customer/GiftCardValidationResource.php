<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'valid' => true,
            'balance' => $this->getRawOriginal('balance'),
            'currency' => $this->currency,
            'recipient_name' => $this->recipient_name,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
