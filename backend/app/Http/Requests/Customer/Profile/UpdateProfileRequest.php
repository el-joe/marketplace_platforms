<?php

namespace App\Http\Requests\Customer\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = auth('customer')->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
        ];
    }
}
