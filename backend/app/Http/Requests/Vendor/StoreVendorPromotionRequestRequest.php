<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorPromotionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_listing_id' => ['required', 'uuid', 'exists:vendor_listings,id'],
            'celebrity_marketer_ids' => ['required', 'array', 'min:1', 'max:10'],
            'celebrity_marketer_ids.*' => ['required', 'uuid', 'distinct', 'exists:marketers,id'],
            'vendor_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
