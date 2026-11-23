<?php

namespace App\Http\Requests\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_or_phone' => ['required', 'string', 'max:255'],
            // Storefront language: the email and the reset link use it.
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(['ar', 'en'])],
        ];
    }

    public function resolvedLocale(): string
    {
        return $this->input('locale') ?: app()->getLocale();
    }
}
