<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->getRawOriginal('balance'),
            'pending_balance' => $this->getRawOriginal('pending_balance'),
            'currency' => $this->currency,
            'is_frozen' => $this->is_frozen,
            'frozen_reason' => $this->frozen_reason,
        ];
    }
}
