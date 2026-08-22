<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardResource extends JsonResource
{
    public function __construct($resource, private readonly bool $showFullCode = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->maskCode(),
            'denomination' => $this->denomination,
            'balance' => $this->balance,
            'currency' => $this->currency,
            'status' => $this->status,
            'recipient_name' => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'personal_message' => $this->personal_message,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskCode(): string
    {
        if ($this->showFullCode) {
            return $this->code;
        }

        $segments = explode('-', $this->code);
        $last = end($segments);

        return "NOON-****-****-{$last}";
    }
}
