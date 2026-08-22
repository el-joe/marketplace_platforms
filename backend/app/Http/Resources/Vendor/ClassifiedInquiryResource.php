<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassifiedInquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'buyer_name'    => $this->whenLoaded('customer', fn () => $this->maskName($this->customer->name ?? '')),
            'message'       => $this->message,
            'contact_phone' => $this->contact_phone,
            'status'        => $this->status?->value,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskName(string $name): string
    {
        $parts = explode(' ', trim($name));

        return collect($parts)->map(function (string $part, int $i) {
            if ($i === 0) {
                return $part;
            }
            return mb_substr($part, 0, 1) . str_repeat('*', max(0, mb_strlen($part) - 1));
        })->implode(' ');
    }
}
