<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vendorCountryId = auth('vendor')->user()?->vendor?->country_id;

        return [
            'name'              => ['sometimes', 'required', 'string', 'max:150'],
            'total_capacity_m3' => ['nullable', 'numeric', 'min:0'],
            'city_id'           => ['nullable', 'uuid', Rule::exists('cities', 'id')->where('country_id', $vendorCountryId)],
            'area'              => ['nullable', 'string', 'max:255'],
            'street_address'    => ['sometimes', 'required', 'string', 'max:255'],
            'building'          => ['nullable', 'string', 'max:100'],
        ];
    }
}
