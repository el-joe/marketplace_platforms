<?php

namespace App\Http\Requests\Vendor\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'account_holder_name' => ['required', 'string', 'max:150'],
            'bank_name'           => ['required', 'string', 'max:150'],
            'iban'                => ['nullable', 'string', 'max:50'],
            'account_number'      => ['nullable', 'string', 'max:50'],
            'swift_code'          => ['nullable', 'string', 'max:20'],
            'currency'            => ['required', 'string', 'size:3'],
        ];
    }
}
