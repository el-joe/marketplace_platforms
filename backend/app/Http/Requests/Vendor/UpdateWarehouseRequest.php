<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'string', 'max:150'],
            'city_id'           => ['nullable', 'uuid', 'exists:cities,id'],
            'area'              => ['nullable', 'string', 'max:255'],
            'street_address'    => ['nullable', 'string', 'max:255'],
            'building'          => ['nullable', 'string', 'max:100'],
            'total_capacity_m3' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
