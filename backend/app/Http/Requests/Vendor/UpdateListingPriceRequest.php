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
            'price'            => ['required', 'numeric', 'decimal:0,2', 'min:1'],
            'compare_at_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:1', 'gt:price'],
        ];
    }
}
