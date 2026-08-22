<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlashSaleSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'flash_sale_id'           => $this->flash_sale_id,
            'vendor_listing_id'       => $this->vendor_listing_id,
            'status'                  => $this->status?->value,
            'flash_price'             => $this->flash_price,
            'original_price'          => $this->original_price,
            'calculated_discount_pct' => (float) $this->calculated_discount_pct,
            'flash_price_currency'    => $this->flash_price_currency,
            'max_quantity_total'      => $this->max_quantity_total,
            'max_quantity_per_customer' => $this->max_quantity_per_customer,
            'quantity_sold'           => $this->quantity_sold,
            'quantity_remaining'      => $this->quantity_remaining,
            'vendor_notes'            => $this->vendor_notes,
            'rejection_reason'        => $this->rejection_reason,
            'rejection_code'          => $this->rejection_code,
            'submitted_at'            => $this->submitted_at?->toISOString(),
            'reviewed_at'             => $this->reviewed_at?->toISOString(),
            'approved_at'             => $this->approved_at?->toISOString(),
            'created_at'              => $this->created_at?->toISOString(),
            // admin_notes intentionally omitted — internal only.
        ];
    }
}
