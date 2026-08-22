<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping method option as shown on the checkout shipping-methods screen,
 * with per-request calculated fee/COD data merged in.
 * Wrap with: new CheckoutShippingMethodResource($method, $calc)
 * where $calc = ['fee', 'is_free', 'cod_extra_fee', 'cod_available'].
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
            'delivery_days_min' => $this->min_delivery_days,
            'delivery_days_max' => $this->max_delivery_days,
            'fee' => $this->calc['fee'],
            'is_free' => $this->calc['is_free'],
            'cod_extra_fee' => $this->calc['cod_extra_fee'],
            'cod_available' => $this->calc['cod_available'],
        ];
    }
}
