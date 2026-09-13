<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_type'        => ['nullable', 'string', 'in:vendor,admin'],
            'vendor_listing_id'   => ['required_unless:listing_type,admin', 'nullable', 'uuid', 'exists:vendor_listings,id'],
            'admin_listing_id' => ['required_if:listing_type,admin', 'nullable', 'uuid', 'exists:admin_listings,id'],
            'quantity'            => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_method_id'  => ['nullable', 'uuid', 'exists:shipping_methods,id'],
            'warranty_plan_id'    => ['nullable', 'uuid', 'exists:warranty_plans,id'],
            'custom_attribute_values' => ['nullable', 'array'],
            'custom_attribute_values.*.product_custom_attribute_id' => ['required_with:custom_attribute_values', 'uuid', 'exists:product_custom_attributes,id'],
            'custom_attribute_values.*.value' => ['required_with:custom_attribute_values', 'string', 'max:1000'],
        ];
    }
}
