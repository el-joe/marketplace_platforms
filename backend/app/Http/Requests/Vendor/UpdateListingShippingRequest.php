<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListingShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primary_shipping_method_id' => ['required', 'uuid', 'exists:shipping_methods,id'],
        ];
    }
}
