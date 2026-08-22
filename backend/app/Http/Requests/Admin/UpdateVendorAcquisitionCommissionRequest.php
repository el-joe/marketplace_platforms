<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorAcquisitionCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commission_rate' => ['required', 'integer', 'min:1', 'max:10000'],
            'monthly_min_sales' => ['required', 'integer', 'min:0'],
            'monthly_max_sales' => ['required', 'integer', 'gte:monthly_min_sales'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
