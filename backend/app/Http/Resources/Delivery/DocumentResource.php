<?php

namespace App\Http\Resources\Delivery;

use App\Enums\DeliveryAgentDocumentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'document_type'    => $this->document_type?->value,
            'status'           => $this->status?->value,
            'expires_at'       => $this->expires_at?->toDateString(),
            'rejection_reason' => $this->when(
                $this->status === DeliveryAgentDocumentStatus::Rejected,
                $this->rejection_reason
            ),
            'verified_at'      => $this->verified_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
