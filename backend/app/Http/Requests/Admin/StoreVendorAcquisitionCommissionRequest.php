<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorAcquisitionCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_id' => ['required', 'uuid', Rule::exists('admins', 'id')->where('status', 'active')],
            'commission_rate' => ['required', 'integer', 'min:1', 'max:10000'],
            'monthly_min_sales' => ['required', 'integer', 'min:0'],
            'monthly_max_sales' => ['required', 'integer', 'gte:monthly_min_sales'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
