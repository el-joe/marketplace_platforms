<?php

namespace App\Http\Requests\Delivery\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:delivery_api'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
