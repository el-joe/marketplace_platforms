<?php

namespace App\Http\Requests\Customer\Order;

use Illuminate\Foundation\Http\FormRequest;

class UploadBankTransferProofRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
