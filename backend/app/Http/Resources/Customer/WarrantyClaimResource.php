<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim_number' => $this->claim_number,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'issue_type' => $this->issue_type,
            'issue_description' => $this->issue_description,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'warranty_expires_at' => $this->warranty_expires_at?->toDateString(),
            'covered_by_platform_warranty' => (bool) $this->covered_by_platform_warranty,
            'evidence_files' => $this->evidence_files,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'product' => [
                'name' => $this->product?->name_en,
                'image' => $this->product?->images?->firstWhere('is_primary', true)?->url
                    ?? $this->product?->images?->first()?->url,
            ],
            'vendor' => [
                'name' => $this->vendor?->name,
            ],
            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages
                    ->where('is_internal_note', false)
                    ->values()
                    ->map(fn ($message) => [
                        'id' => $message->id,
                        'sender_role' => $message->sender_role,
                        'message' => $message->message,
                        'created_at' => $message->created_at?->toIso8601String(),
                    ]);
            }),
        ];
    }
}
