<?php

namespace App\Http\Resources\Vendor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'store_name'                  => $this->store_name,
            'store_slug'                  => $this->store_slug,
            'store_description'           => $this->store_description,
            'business_name'               => $this->business_name,
            'business_type'               => $this->business_type?->value,
            'business_registration_number'=> $this->business_registration_number,
            'tax_id'                      => $this->tax_id,
            'contact_email'               => $this->contact_email,
            'contact_phone'               => $this->contact_phone,
            'whatsapp_number'             => $this->whatsapp_number,
            'global_status'               => $this->global_status?->value,
            'approved_at'                 => $this->approved_at?->toISOString(),
            'onboarding_completed_at'     => $this->onboarding_completed_at?->toISOString(),
            'store_rating_avg'            => $this->store_rating_avg,
            'store_rating_count'          => $this->store_rating_count,
            'positive_rating_pct'         => $this->positive_rating_pct,
            'partner_since_years'         => $this->partner_years,
            'warranty_months'             => $this->warranty_months,
            'easy_returns_enabled'        => (bool) $this->easy_returns_enabled,
            'secure_payments_enabled'     => (bool) $this->secure_payments_enabled,
            'country_id'                  => $this->country_id,
            'logo_url'                    => $this->avatar ? asset('storage/' . $this->avatar) : null,
        ];
    }
}
