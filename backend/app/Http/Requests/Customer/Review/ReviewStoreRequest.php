<?php

namespace App\Http\Requests\Customer\Review;

use Illuminate\Foundation\Http\FormRequest;

class ReviewStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'order_item_id'    => ['required', 'uuid', 'exists:order_items,id'],
            'vendor_listing_id'=> ['nullable', 'uuid', 'exists:vendor_listings,id'],
            'admin_listing_id' => ['nullable', 'uuid', 'exists:admin_listings,id'],
            'rating'           => ['required', 'integer', 'min:1', 'max:5'],
            'comment'          => ['nullable', 'string', 'max:5000'],
            'images'           => ['nullable', 'array', 'max:5'],
            'images.*'         => ['image', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('vendor_listing_id') && $this->filled('admin_listing_id')) {
                $validator->errors()->add('listing', 'Provide either vendor_listing_id or admin_listing_id, not both.');
            }
        });
    }
}
