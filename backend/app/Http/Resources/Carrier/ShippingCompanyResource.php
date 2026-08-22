<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'legal_name'    => $this->legal_name,
            'country'       => $this->whenLoaded('country', fn () => [
                'id'      => $this->country->id,
                'name_en' => $this->country->name_en,
                'name_ar' => $this->country->name_ar,
            ]),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'logo_url'      => $this->logo_path
                ? asset('storage/' . $this->logo_path)
                : null,
            'status'        => $this->status?->value,
            'can_supervisors_receive_all_notifications' => $this->can_supervisors_receive_all_notifications,
            'approved_at'   => $this->approved_at?->toIso8601String(),
        ];
    }
}
