<?php

namespace App\Http\Requests\Customer\Travel;

use Illuminate\Foundation\Http\FormRequest;

class SignContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_data' => ['required', 'string'],
        ];
    }
}
