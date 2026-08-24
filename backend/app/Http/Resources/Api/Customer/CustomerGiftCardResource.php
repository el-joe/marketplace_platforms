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
            'amount' => $this->amount,
            'remaining_balance' => $this->remaining_balance,
            'currency' => $this->currency_code,
            'status' => $this->status,
            'recipient_email' => $this->recipient_email,
            'recipient_name' => $this->recipient_name,
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'purchased_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
