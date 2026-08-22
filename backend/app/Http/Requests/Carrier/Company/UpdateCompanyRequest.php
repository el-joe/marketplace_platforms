<?php

namespace App\Http\Requests\Carrier\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'logo'          => ['nullable', 'image', 'max:2048'],
        ];
    }
}
