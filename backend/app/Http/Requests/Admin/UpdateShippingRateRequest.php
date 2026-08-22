<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination_zone_id'      => ['required', 'exists:shipping_zones,id'],
            'shipping_method_id'        => ['required', 'exists:shipping_methods,id'],
            'carrier_id'                => ['nullable', 'exists:shipping_carriers,id'],
            'origin_zone_id'            => ['nullable', 'exists:shipping_zones,id'],
            'base_fee'                  => ['required', 'numeric', 'min:0'],
            'carrier_rate'              => ['required', 'numeric', 'min:0'],
            'carrier_rate_per_kg'       => ['required', 'numeric', 'min:0'],
            'rate_per_kg'               => ['numeric', 'min:0'],
            'min_weight_grams'          => ['integer', 'min:0'],
            'volumetric_divisor'        => ['integer', 'min:1', 'max:10000'],
            'free_shipping_threshold'   => ['nullable', 'numeric', 'min:0'],
            'cod_extra_fee'             => ['numeric', 'min:0'],
            'is_active'                 => ['boolean'],
        ];
    }
}
