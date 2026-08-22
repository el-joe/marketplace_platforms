<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class FlashSaleSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT');

        return [
            'vendor_listing_id'        => [$isUpdate ? 'prohibited' : 'required', 'uuid'],
            'flash_price'              => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1'],
            'quantity_committed'       => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1'],
            'max_quantity_per_customer' => ['nullable', 'integer', 'min:1'],
            'vendor_notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
