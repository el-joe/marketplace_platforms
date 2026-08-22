<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => $this->balance,
            'currency' => $this->currency,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
