<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RedeemGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:16', 'alpha_num'],
            'pin' => ['required', 'string', 'size:4', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.size' => 'Gift card code must be exactly 16 characters.',
            'code.alpha_num' => 'Gift card code must be alphanumeric.',
            'pin.size' => 'PIN must be exactly 4 digits.',
            'pin.digits' => 'PIN must contain only digits.',
        ];
    }
}
