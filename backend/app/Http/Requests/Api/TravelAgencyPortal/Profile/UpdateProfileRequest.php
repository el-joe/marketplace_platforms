<?php

namespace App\Http\Requests\Api\TravelAgencyPortal\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
