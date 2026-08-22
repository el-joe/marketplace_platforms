<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class WishlistStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_listing_id' => ['required', 'uuid', 'exists:vendor_listings,id'],
        ];
    }
}
