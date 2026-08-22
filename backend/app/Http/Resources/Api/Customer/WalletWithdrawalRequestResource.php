<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletWithdrawalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->getRawOriginal('amount'),
            'currency' => $this->currency,
            'bank_name' => $this->bank_name,
            'bank_iban' => $this->bank_iban,
            'status' => $this->status,
        ];
    }
}
