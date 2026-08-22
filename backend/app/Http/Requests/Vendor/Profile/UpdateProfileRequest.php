<?php

namespace App\Http\Requests\Vendor\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'store_description' => ['nullable', 'string', 'max:2000'],
            'contact_email'     => ['nullable', 'email', 'max:150'],
            'contact_phone'     => ['nullable', 'string', 'max:30'],
            'whatsapp_number'   => ['nullable', 'string', 'max:30'],
            'warranty_months'   => ['nullable', 'integer', 'min:0', 'max:120'],
            'easy_returns_enabled' => ['nullable', 'boolean'],
            'secure_payments_enabled' => ['nullable', 'boolean'],
        ];
    }
}
