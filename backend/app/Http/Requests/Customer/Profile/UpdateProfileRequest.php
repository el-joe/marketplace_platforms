<?php

namespace App\Http\Requests\Customer\Profile;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Older clients send "prefer_not_to_say"; it now means "not specified" (NULL).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('gender') === 'prefer_not_to_say') {
            $this->merge(['gender' => null]);
        }
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
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'nationality' => ['nullable', 'string', 'max:100'],
            'is_tourist' => ['nullable', 'boolean'],
        ];
    }
}
