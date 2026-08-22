<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlashSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'name_en'                  => $this->name_en,
            'name_ar'                  => $this->name_ar,
            'description_en'           => $this->description_en,
            'description_ar'           => $this->description_ar,
            'status'                   => $this->status?->value,
            'submission_opens_at'      => $this->submission_opens_at?->toISOString(),
            'submission_closes_at'     => $this->submission_closes_at?->toISOString(),
            'sale_starts_at'           => $this->sale_starts_at?->toISOString(),
            'sale_ends_at'             => $this->sale_ends_at?->toISOString(),
            'min_discount_pct'         => (float) $this->min_discount_pct,
            'max_products_per_seller'  => $this->max_products_per_seller,
            'is_featured'              => (bool) $this->is_featured,
            'is_exclusive'             => (bool) $this->is_exclusive,
            'commission_override_pct'  => $this->commission_override_pct ? (float) $this->commission_override_pct : null,
            'eligible_categories'      => $this->eligible_categories,
            // Invitation status for this vendor — null if not invite-only or no record.
            'invitation'               => $this->when(
                $this->relationLoaded('vendorInvitation') && $this->vendorInvitation !== null,
                fn () => [
                    'status'          => $this->vendorInvitation->status?->value,
                    'invitation_type' => $this->vendorInvitation->invitation_type,
                    'slots_allocated' => $this->vendorInvitation->slots_allocated,
                ]
            ),
        ];
    }
}
