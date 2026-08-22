<?php

namespace App\Http\Requests\Vendor;

use App\Enums\VendorListingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Vendors may only self-serve active/paused; admin-gated statuses are excluded
        return [
            'status' => ['required', 'string', Rule::in([
                VendorListingStatus::Active->value,
                VendorListingStatus::Paused->value,
            ])],
        ];
    }
}
