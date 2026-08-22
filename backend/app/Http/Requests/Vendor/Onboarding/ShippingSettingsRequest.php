<?php

namespace App\Http\Requests\Vendor\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class ShippingSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'default_handling_time_hours'         => ['nullable', 'integer', 'min:1', 'max:720'],
            'warehouse_address'                   => ['nullable', 'array'],
            'warehouse_address.label'             => ['nullable', 'string', 'max:100'],
            'warehouse_address.address_line_1'    => ['required_with:warehouse_address', 'string', 'max:255'],
            'warehouse_address.address_line_2'    => ['nullable', 'string', 'max:255'],
            'warehouse_address.city'              => ['nullable', 'string', 'max:100'],
            'warehouse_address.state'             => ['nullable', 'string', 'max:100'],
            'warehouse_address.postal_code'       => ['nullable', 'string', 'max:20'],
            'warehouse_address.phone'             => ['nullable', 'string', 'max:30'],
        ];
    }
}
