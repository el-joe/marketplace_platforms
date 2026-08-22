<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_holder_name' => ['required', 'string', 'max:150'],
            'bank_name'           => ['required', 'string', 'max:150'],
            'account_number'      => ['required', 'string', 'max:50'],
            'iban'                => ['nullable', 'string', 'max:50'],
            'branch'              => ['nullable', 'string', 'max:150'],
            'swift_code'          => ['nullable', 'string', 'max:20'],
            'currency'            => ['required', 'string', 'size:3'],
        ];
    }
}
