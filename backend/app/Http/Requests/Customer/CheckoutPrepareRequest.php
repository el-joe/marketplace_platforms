<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutPrepareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_id'                 => ['required', 'integer', 'exists:addresses,id'],
            'receiver_id'                => ['nullable', 'uuid', 'exists:customer_receivers,id'],
            'country_payment_gateway_id' => ['required', 'uuid', 'exists:country_payment_gateways,id'],
            'coupon_code'                => ['nullable', 'string', 'max:50'],
            'warranty_selections'        => ['nullable', 'array'],
            'warranty_selections.*.listing_id'       => ['required_with:warranty_selections', 'uuid'],
            'warranty_selections.*.warranty_plan_id' => ['required_with:warranty_selections', 'uuid'],
        ];
    }
}
