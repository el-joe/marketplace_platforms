<?php

namespace App\Http\Requests\Customer\Receiver;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'is_default' => ['boolean'],
        ];
    }
}
