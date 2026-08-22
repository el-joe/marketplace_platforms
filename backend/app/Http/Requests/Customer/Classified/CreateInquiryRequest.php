<?php

namespace App\Http\Requests\Customer\Classified;

use Illuminate\Foundation\Http\FormRequest;

class CreateInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'       => ['required', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
