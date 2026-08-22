<?php

namespace App\Http\Requests\Api\TravelAgencyPortal\Fcm;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ];
    }
}
