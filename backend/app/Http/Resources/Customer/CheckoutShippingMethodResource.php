<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping method option as shown on the checkout shipping-methods screen,
 * with per-request calculated fee/COD data merged in.
 * Wrap with: new CheckoutShippingMethodResource($method, $calc)
 * where $calc = ['fee', 'is_free', 'cod_extra_fee', 'cod_available', optional 'is_available', 'unavailable_reason', 'distance'].
 */
class CheckoutShippingMethodResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly array $calc)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'badge_label_en' => $this->badge_label_en,
            'badge_label_ar' => $this->badge_label_ar,
            'badge_color_hex' => $this->badge_color_hex,
            'badge_text_color_hex' => $this->badge_text_color_hex,
            'badge_icon_color_hex' => $this->badge_icon_color_hex,
            'delivery_days_min' => $this->min_delivery_days,
            'delivery_days_max' => $this->max_delivery_days,
            'fee' => $this->calc['fee'],
            'is_free' => $this->calc['is_free'],
            'cod_extra_fee' => $this->calc['cod_extra_fee'],
            'cod_available' => $this->calc['cod_available'],
            // Distance-based delivery (client item #19): false when the address is beyond the method's max radius.
            'is_available' => $this->calc['is_available'] ?? true,
            'unavailable_reason' => $this->calc['unavailable_reason'] ?? null,
            'distance' => $this->calc['distance'] ?? null,
        ];
    }
}
