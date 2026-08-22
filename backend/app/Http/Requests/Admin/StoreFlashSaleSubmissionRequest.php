<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlashSaleSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_type' => ['required', Rule::in(['vendor', 'admin'])],
            'vendor_id' => ['required_if:submission_type,vendor', 'nullable', 'exists:vendors,id'],
            'vendor_listing_id' => ['required_if:submission_type,vendor', 'nullable', 'exists:vendor_listings,id'],
            'admin_listing_id' => ['required_if:submission_type,admin', 'nullable', 'exists:admin_listings,id'],
            'flash_price' => ['required', 'integer', 'min:1'],
            'original_price' => ['required', 'integer', 'min:1'],
            'max_quantity_total' => ['required', 'integer', 'min:1'],
            'max_quantity_per_customer' => ['nullable', 'integer', 'min:1'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasVendorListing = $this->filled('vendor_listing_id');
            $hasAdminListing = $this->filled('admin_listing_id');

            if ($hasVendorListing === $hasAdminListing) {
                $validator->errors()->add(
                    'vendor_listing_id',
                    __('admin.flash_sales.exactly_one_listing_required')
                );
            }
        });
    }
}
