<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListingPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price'            => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1', 'gt:price'],
        ];
    }
}
