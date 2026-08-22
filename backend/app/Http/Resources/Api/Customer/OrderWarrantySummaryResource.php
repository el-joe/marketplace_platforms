<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderWarrantySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_snapshot' => $this->plan_snapshot,
            'price_paid' => $this->price_paid,
            'currency' => $this->currency,
            'status' => $this->status,
            'coverage_starts_at' => $this->coverage_starts_at?->toDateString(),
            'coverage_ends_at' => $this->coverage_ends_at?->toDateString(),
        ];
    }
}
