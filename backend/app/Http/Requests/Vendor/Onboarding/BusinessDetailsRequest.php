<?php

namespace App\Http\Requests\Vendor\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class BusinessDetailsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_name'                => ['required', 'string', 'max:200'],
            'business_type'                => ['required', 'in:individual,sole_prop,llc,corp'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id'                       => ['required', 'string', 'max:100'],
        ];
    }
}
