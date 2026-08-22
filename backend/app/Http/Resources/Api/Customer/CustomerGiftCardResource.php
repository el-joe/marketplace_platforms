<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerGiftCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'denomination' => $this->getRawOriginal('denomination'),
            'balance' => $this->getRawOriginal('balance'),
            'currency' => $this->currency,
            'status' => $this->status,
            'recipient_email' => $this->recipient_email,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
