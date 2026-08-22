<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                          => ['required', 'array', 'min:1', 'max:50'],
            'items.*.listing_type'           => ['nullable', 'string', 'in:vendor,admin'],
            'items.*.vendor_listing_id'      => ['required_unless:items.*.listing_type,admin', 'nullable', 'uuid', 'exists:vendor_listings,id'],
            'items.*.admin_listing_id' => ['required_if:items.*.listing_type,admin', 'nullable', 'uuid', 'exists:admin_listings,id'],
            'items.*.quantity'               => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.shipping_method_id'     => ['nullable', 'uuid', 'exists:shipping_methods,id'],
        ];
    }
}
