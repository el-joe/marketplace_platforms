<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SellerDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'document_type'    => $this->documentType?->code,
            'document_type_name' => $this->documentType?->name_en,
            'status'           => $this->status?->value,
            'rejection_reason' => $this->rejection_reason,
            'expires_at'       => $this->expires_at?->toISOString(),
            'verified_at'      => $this->verified_at?->toISOString(),
            'preview_url'      => $this->file_path
                ? Storage::disk('public')->temporaryUrl($this->file_path, now()->addMinutes(15))
                : null,
            'created_at'       => $this->created_at->toISOString(),
        ];
    }
}
