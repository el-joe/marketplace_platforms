<?php

namespace App\Http\Requests\Delivery\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp_code' => ['required', 'string', 'digits:6'],
        ];
    }
}
